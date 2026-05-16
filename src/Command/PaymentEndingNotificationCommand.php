<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Sends notifications about rented courses ending tomorrow.'
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
        private readonly Twig $twig,

        #[Autowire(param: 'app.payment_from_email')]
        private readonly string $fromEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = new \DateTimeImmutable('tomorrow 00:00:00');
        $to = $from->modify('+1 day');

        $rentalsByEmail = $this->groupRentalsByEmail(
            $this->transactionRepository->findEndingRentals($from, $to)
        );

        foreach ($rentalsByEmail as $email => $rentals) {
            $message = new Email()
                ->from($this->fromEmail)
                ->to($email)
                ->subject('Срок аренды курсов подходит к концу')
                ->html($this->twig->render('email/ending_notification.html.twig', [
                    'rentals' => $rentals,
                ]));

            $this->mailer->send($message);
        }

        $output->writeln(sprintf('Sent %d notification(s).', count($rentalsByEmail)));

        return Command::SUCCESS;
    }

    private function groupRentalsByEmail(array $rentals): array
    {
        $grouped = [];

        foreach ($rentals as $rental) {
            $grouped[$rental['email']][] = [
                'course_name' => $rental['course_name'],
                'valid_until' => $rental['valid_until'],
            ];
        }

        return $grouped;
    }
}
