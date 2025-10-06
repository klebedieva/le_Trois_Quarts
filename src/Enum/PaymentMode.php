<?php
namespace App\Enum;

enum PaymentMode: string {
    case CARD = 'card';
    case CASH = 'cash';
    case TICKETS = 'tickets';
}