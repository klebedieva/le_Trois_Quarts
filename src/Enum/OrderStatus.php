<?php
namespace App\Enum;

enum OrderStatus: string {
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}