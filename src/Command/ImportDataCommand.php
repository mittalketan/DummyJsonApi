<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Services\DummyApiCallService;
use App\Entity\User;
use App\Entity\Bank;
use App\Entity\Post;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportDataCommand extends Command
{
    protected static $defaultName = 'app:import:data';
    protected static $defaultDescription = 'Command is use fetches Data from the https://dummyjson.com/ API and save in DB';

    private $dummyApiCallService;
    private $doctrine;


    public function __construct(DummyApiCallService $dummyApiCallService, ManagerRegistry $doctrine)
    {
        $this->dummyApiCallService = $dummyApiCallService;
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('limit', InputArgument::OPTIONAL, 'Limit api results', 10)
            ->addArgument('skip', InputArgument::OPTIONAL, 'Skip api records', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = $input->getArgument('limit');
        $skip = $input->getArgument('skip');

        if (!$skip) {
            $skip = 0;
        }
        if (!$limit) {
            $limit = 10;
        }
        $entityManager = $this->doctrine->getManager();

        $users =  $this->getUsers($limit, $skip);

        $progressBar = new ProgressBar($output, count($users['users']));
        // starts and displays the progress bar
        $progressBar->start();
        $this->processUsers($users['users'], $entityManager, $progressBar);
        $progressBar->finish();

        $io->success(count($users['users']) . ' user(s) updated or inserted');

        return Command::SUCCESS;
    }

    /**
     * Fetch user from API
     *
     * @param int $limit
     * @param int $skip
     * @return array
     */
    private function getUsers(int $limit, int $skip): array
    {
        return $this->dummyApiCallService->fetchGitHubInformation('users', $limit, $skip);
    }

    /**
     * Fetch post from API
     *
     * @param integer $user_id
     * @param integer $limit
     * @param integer $skip
     * @return array
     */
    private function getPostsforUser(int $user_id, int $limit, int $skip): array
    {
        return $this->dummyApiCallService->fetchGitHubInformation('posts/user/' . $user_id, $limit, $skip);
    }

    /**
     * Process Users
     *
     * @param array $users
     * @param Object $entityManager
     * @param Object $progressBar
     * @return void
     */
    private function processUsers(array $users, $entityManager, $progressBar)
    {
        foreach ($users as $user) {
            $this->addOrUpdateUser($user, $entityManager);
            $progressBar->advance();
        }
    }

    /**
     * Add or update User in DB
     *
     * @param Array $userArr
     * @param Object $entityManager
     * @return void
     */
    private function addOrUpdateUser($userArr, $entityManager)
    {
        $userRepository = $this->doctrine->getRepository(User::class);

        $user = $userRepository->findOneby(['dummy_id' => $userArr['id']]);
        if (!$user) {
            $user = new User();
            $bank = new Bank();
        } else {
            $bank = $user->getBank();
            if (is_null($bank)) {
                $bank = new Bank();
            }
        }

        $user->setDummyId($userArr['id']);
        $user->setFirstName($userArr['firstName']);
        $user->setLastName($userArr['lastName']);
        $user->setEmail($userArr['email']);
        $user->setPhone($userArr['phone']);
        $user->setUserName($userArr['username']);
        $user->setHeight($userArr['height']);
        $user->setWeight($userArr['weight']);
        $user->setAddress($userArr['address']['address']);
        $user->setCity($userArr['address']['city']);
        $user->setBirthDate(\DateTime::createFromFormat('Y-m-d', $userArr['birthDate']));

        $user->setBank($this->saveBankDetails($bank, $userArr, $entityManager));

        $entityManager->persist($user);
        $entityManager->flush();

        $this->processUsersPost($user, $userArr['id'], $entityManager);
    }

    /**
     * save bank details for user
     *
     * @param [type] $bank
     * @param [type] $userArr
     * @param [type] $entityManager
     * @return void
     */
    private function saveBankDetails($bank, $userArr, $entityManager)
    {

        $bank->setCardExpire($userArr['bank']['cardExpire']);
        $bank->setCardNumber($userArr['bank']['cardNumber']);
        $bank->setCardType($userArr['bank']['cardType']);
        $bank->setCurrency($userArr['bank']['currency']);
        $bank->setIban($userArr['bank']['iban']);
        $entityManager->persist($bank);
        $entityManager->flush();

        return $bank;
    }

    /**
     * Fetch post by User
     *
     * @param User $user
     * @param int $dummy_id
     * @param Object $entityManager
     * @return void
     */
    private function processUsersPost($user, int $dummy_id, $entityManager)
    {
        $postsArray =  $this->getPostsforUser($dummy_id, 0, 0);
        foreach ($postsArray['posts'] as $post) {
            $this->addOrUpdatePost($post, $user, $entityManager);
        }
    }

    /**
     * Add or update Post in DB
     *
     * @param array $postObj
     * @param User $user
     * @param Object $entityManager
     * @return void
     */
    private function addOrUpdatePost($postObj, $user, $entityManager)
    {
        $postRepository = $this->doctrine->getRepository(Post::class);

        $post = $postRepository->findOneby(['dummy_id' => $postObj['id']]);
        if (!$post) {
            $post = new Post();
        }

        $post->setDummyId($postObj['id']);
        $post->setTitle($postObj['title']);
        $post->setBody($postObj['body']);
        $post->setUserId($user);
        $post->setReactions($postObj['reactions']);

        $entityManager->persist($post);
        $entityManager->flush();
    }
}
