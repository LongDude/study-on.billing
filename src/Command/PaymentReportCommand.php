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
    name: 'payment:report',
    description: 'Sends a monthly paid courses report.'
)]
class PaymentReportCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
        private readonly Twig $twig,
        #[Autowire(param: 'app.payment_report_email')]
        private readonly string $reportEmail,

        #[Autowire(param: 'app.payment_from_email')]
        private readonly string $fromEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $periodStart = new \DateTimeImmutable('first day of previous month 00:00:00');
        $periodEnd = new \DateTimeImmutable('first day of this month 00:00:00');

        $reportRows = array_map(
            fn (array $row): array => [
                'course_name' => $row['course_name'],
                'course_type' => $this->formatCourseType((int) $row['course_type']),
                'payments_count' => (int) $row['payments_count'],
                'total_sum' => (float) $row['total_sum'],
            ],
            $this->transactionRepository->getPaidCoursesReport($periodStart, $periodEnd)
        );

        $message = (new Email())
            ->from($this->fromEmail)
            ->to($this->reportEmail)
            ->subject(sprintf(
                'Отчет об оплаченных курсах за период %s - %s',
                $periodStart->format('d.m.Y'),
                $periodEnd->modify('-1 second')->format('d.m.Y')
            ))
            ->html($this->twig->render('email/report.html.twig', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd->modify('-1 second'),
                'rows' => $reportRows,
            ]));

        $this->mailer->send($message);

        $output->writeln(sprintf('Report sent to %s.', $this->reportEmail));

        return Command::SUCCESS;
    }

    private function formatCourseType(int $courseType): string
    {
        return match ($courseType) {
            1 => 'Аренда',
            2 => 'Покупка',
            default => 'Неизвестно',
        };
    }
}
