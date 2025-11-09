<?php

namespace App\Command;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\TaxCalculationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recalculate-order-totals',
    description: 'Recalculate totals for all orders',
)]
class RecalculateOrderTotalsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaxCalculationService $taxCalculationService
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orderRepository = $this->entityManager->getRepository(Order::class);
        $orders = $orderRepository->findAll();

        $io->note(sprintf('Found %d orders to recalculate', count($orders)));

        $updated = 0;
        foreach ($orders as $order) {
            $this->taxCalculationService->applyOrderTotals($order);
            $this->entityManager->persist($order);
            $updated++;
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully recalculated %d orders', $updated));

        return Command::SUCCESS;
    }
}
