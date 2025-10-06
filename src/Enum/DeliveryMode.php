<?php
namespace App\Enum;

enum DeliveryMode: string {
    case DELIVERY = 'delivery';
    case PICKUP = 'pickup';
}