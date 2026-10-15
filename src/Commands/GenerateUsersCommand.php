<?php

namespace App\Commands;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:generate-users',
    description: 'Creates the demo users (one staff, one non-staff) if they do not exist yet.',
)]
class GenerateUsersCommand extends Command
{
    protected const USERS = [
        ['email' => 'staff@trailcast.test', 'password' => 'staff123', 'roles' => ['ROLE_STAFF']],
        ['email' => 'user@trailcast.test', 'password' => 'user123', 'roles' => []],
    ];

    protected EntityManagerInterface $entityManager;
    protected UserRepository $userRepository;
    protected UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;

        parent::__construct();
    }

    /**
     * Creates the demo users defined in self::USERS, skipping any that already exist.
     *
     * Idempotent: users are looked up by email, so running the command again never
     * creates duplicates or overwrites existing passwords and roles. Each new user is
     * flushed right away, so "Created" is printed only after it has been written.
     *
     * @param InputInterface  $input  Console input (the command takes no arguments or options)
     * @param OutputInterface $output Console output used for progress messages
     *
     * @return int Command::SUCCESS once every demo user exists
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach (self::USERS as $data) {
            if ($this->userRepository->findOneBy(['email' => $data['email']]) !== null) {
                $io->writeln(sprintf('Skipped: %s already exists', $data['email']));
                continue;
            }

            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles($data['roles']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->writeln(sprintf('Created: %s', $data['email']));
        }

        $this->entityManager->flush();
        $io->success('Demo users ready.');

        return Command::SUCCESS;
    }
}
