<?php

namespace App\Command;

use App\Repository\MemberRepository;
use App\Utils\Validator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * A console command that edit a member's password from the database.
 */
class EditPasswordMemberCommand extends Command
{
    protected static $defaultName = 'app:edit-password-member';

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
     * EditPasswordMemberCommand Constructor
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
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription("Edit a member's password from the database")
            ->addArgument('username', InputArgument::REQUIRED, 'The username of an existing member')
            ->addArgument('password', InputArgument::REQUIRED, "The new member password")
            ->setHelp($this->getCommandHelp());
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
        if (null !== $input->getArgument('username') && null !== $input->getArgument('password')) {
            return;
        }

        $this->io->title('Edit a password Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console  app:edit-password-member username password',
            '',
            'Now we\'ll ask you for the value of all the missing command arguments.',
            '',
        ]);

        $username = $this->io->ask('Username', null, [$this->validator, 'validateUsername']);
        $input->setArgument('username', $username);

        $password = $this->io->askHidden('Password (your type will be hidden)', [$this->validator, 'validatePassword']);
        $input->setArgument('password', $password);
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface $input
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $plainPassword = $input->getArgument('password');

        $member = $this->members->findOneBy(['username' => $username]);
        if (null === $member) {
            throw new RuntimeException(sprintf('Member with username "%s" not found.', $username));
        }

        $this->validator->validatePassword($plainPassword);

        $encodedPassword = $this->passwordEncoder->hashPassword($member, $plainPassword);
        $member->setPassword($encodedPassword);
        $this->em->flush();

        $this->io->success(sprintf('Member "%s" (email: %s) was successfully updated.', $member->getUsername(), $member->getEmail()));
        return 0;
    }

    /**
     * @return stringf
     */
    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command edit a member's password from the database:

  <info>php %command.full_name%</info> <comment>username password</comment>

If you omit the argument, the command will ask you to
provide the missing value:

  # command will ask you for the password
  <info>php %command.full_name%</info> <comment>username</comment>

HELP;
    }

}
