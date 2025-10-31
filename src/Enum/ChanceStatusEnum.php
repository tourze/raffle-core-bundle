<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum ChanceStatusEnum: string implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case INIT = 'init';
    case WINNING = 'winning';
    case ORDERED = 'ordered';
    case SHIPPED = 'shipped';
    case RECEIVED = 'received';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::INIT => '初始化',
            self::WINNING => '中奖',
            self::ORDERED => '已下单',
            self::SHIPPED => '已发货',
            self::RECEIVED => '已收货',
            self::EXPIRED => '已过期',
        };
    }
}
