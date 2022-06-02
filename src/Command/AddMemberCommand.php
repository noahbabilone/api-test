<?php

namespace App\Command;

use App\Entity\Member;
use App\Repository\MemberRepository;
use App\Utils\Validator;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use function Symfony\Component\String\u;

/**
 * A console command that creates members and stores them in the database.
 */
class AddMemberCommand extends Command
{
    protected static $defaultName = 'app:add-member';

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var UserPasswordHasherInterface
     */
    private $passwordEncoder;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var MemberRepository
     */
    private $members;

    /**
     * AddMemberCommand Constructor
     */
    public function __construct(
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $encoder,
        Validator                   $validator,
        MemberRepository            $members
    )
    {
        parent::__construct();

        $this->em = $em;
        $this->passwordEncoder = $encoder;
        $this->members = $members;
        $this->validator = $validator;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Creates members and stores them in the database')
            ->setHelp($this->getCommandHelp())
            ->addArgument('username', InputArgument::OPTIONAL, 'The username of the new member')
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain password of the new member')
            ->addArgument('email', InputArgument::OPTIONAL, 'The email of the new member')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'If set, the member is created as an administrator');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null !== $input->getArgument('username') && null !== $input->getArgument('password') && null !== $input->getArgument('email')) {
            return;
        }

        $this->io->title('Add Member Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console app:add-member username password email@example.com',
            '',
            'Now we\'ll ask you for the value of all the missing command arguments.',
        ]);

        // Ask for the username if it's not defined
        $username = $input->getArgument('username');
        if (null !== $username) {
            $this->io->text(' > <info>Username</info>: ' . $username);
        } else {
            $username = $this->io->ask('Username', null, [$this->validator, 'validateUsername']);
            $input->setArgument('username', $username);
        }

        // Ask for the password if it's not defined
        $password = $input->getArgument('password');
        if (null !== $password) {
            $this->io->text(' > <info>Password</info>: ' . u('*')->repeat(u($password)->length()));
        } else {
            $password = $this->io->askHidden('Password (your type will be hidden)', [$this->validator, 'validatePassword']);
            $input->setArgument('password', $password);
        }

        // Ask for the email if it's not defined
        $email = $input->getArgument('email');
        if (null !== $email) {
            $this->io->text(' > <info>Email</info>: ' . $email);
        } else {
            $email = $this->io->ask('Email', null, [$this->validator, 'validateEmail']);
            $input->setArgument('email', $email);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('add-member-command');

        $username = $input->getArgument('username');
        $plainPassword = $input->getArgument('password');
        $email = $input->getArgument('email');
        $isAdmin = $input->getOption('admin');

        $this->validateUserData($username, $plainPassword, $email);

        $member = (new Member())
            ->setUsername($username)
            ->setEmail($email)
            ->setRoles([$isAdmin ? 'ROLE_ADMIN' : 'ROLE_USER']);

        $encodedPassword = $this->passwordEncoder->hashPassword($member, $plainPassword);
        $member->setPassword($encodedPassword);
        $this->em->persist($member);
        $this->em->flush();

        $this->io->success(
            sprintf('%s was successfully created: %s (%s)', $isAdmin ? 'Administrator member' : 'User',
                $member->getUsername(),
                $member->getEmail())
        );

        $event = $stopwatch->stop('add-member-command');
        if ($output->isVerbose()) {
            $this->io->comment(
                sprintf('New member database id: %d / Elapsed time: %.2f ms / Consumed memory: %.2f MB',
                    $member->getId(), $event->getDuration(), $event->getMemory() / (1024 ** 2)));
        }


        return Command::SUCCESS;
    }

    /**
     * @param string $username
     * @param string $plainPassword
     * @param string $email
     * @return void
     */
    private function validateUserData(string $username, string $plainPassword, string $email): void
    {
        $existingMember = $this->members->findOneBy(['username' => $username]);

        if (null !== $existingMember) {
            throw new RuntimeException(sprintf('There is already a member registered with the "%s" username.', $username));
        }

        $this->validator->validatePassword($plainPassword);
        $this->validator->validateEmail($email);

        $existingEmail = $this->members->findOneBy(['email' => $email]);

        if (null !== $existingEmail) {
            throw new RuntimeException(sprintf('There is already a user registered with the "%s" email.', $email));
        }
    }

    /**
     * @return string
     */
    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates new members and saves them in the database:

  <info>php %command.full_name%</info> <comment>username password email</comment>

By default the command creates regular members. To create administrator members,
add the <comment>--admin</comment> option:

  <info>php %command.full_name%</info> username password email <comment>--admin</comment>

If you omit any of the three required arguments, the command will ask you to
provide the missing values:

  # command will ask you for the email
  <info>php %command.full_name%</info> <comment>username password</comment>

  # command will ask you for the email and password
  <info>php %command.full_name%</info> <comment>username</comment>
HELP;
    }
}
