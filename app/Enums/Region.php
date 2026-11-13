<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

/**
 * Where venues and artists are based and where events happen: the big metros on their own, then
 * the rest of each province, so the whole country is covered.
 */
enum Region: string
{
    use HasEnumOptions;

    case Johannesburg = 'johannesburg';
    case Pretoria = 'pretoria';
    case GautengOther = 'gauteng-other';
    case CapeTown = 'cape-town';
    case WesternCapeOther = 'western-cape-other';
    case Durban = 'durban';
    case KwaZuluNatalOther = 'kwazulu-natal-other';
    case EasternCape = 'eastern-cape';
    case FreeState = 'free-state';
    case Limpopo = 'limpopo';
    case Mpumalanga = 'mpumalanga';
    case NorthWest = 'north-west';
    case NorthernCape = 'northern-cape';

    public function label(): string
    {
        return match ($this) {
            self::Johannesburg => 'Johannesburg',
            self::Pretoria => 'Pretoria',
            self::GautengOther => 'Gauteng (other)',
            self::CapeTown => 'Cape Town',
            self::WesternCapeOther => 'Western Cape (other)',
            self::Durban => 'Durban',
            self::KwaZuluNatalOther => 'KwaZulu-Natal (other)',
            self::EasternCape => 'Eastern Cape',
            self::FreeState => 'Free State',
            self::Limpopo => 'Limpopo',
            self::Mpumalanga => 'Mpumalanga',
            self::NorthWest => 'North West',
            self::NorthernCape => 'Northern Cape',
        };
    }
}
