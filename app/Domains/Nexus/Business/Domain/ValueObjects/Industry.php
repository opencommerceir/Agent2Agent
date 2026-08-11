<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

/**
 * The roadmap (docs/nexus-roadmap.md, Phase 1) asks for "۲۰+ دسته"
 * (20+ industry categories) in the onboarding flow's industry picker.
 * A closed enum keeps it type-safe rather than a free-text column — same
 * reasoning Core's TenantStatus/OrganizationMemberRole already follow.
 */
enum Industry: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';
    case Manufacturing = 'manufacturing';
    case FoodAndBeverage = 'food_beverage';
    case Construction = 'construction';
    case RealEstate = 'real_estate';
    case Technology = 'technology';
    case Healthcare = 'healthcare';
    case Education = 'education';
    case TransportationAndLogistics = 'transportation_logistics';
    case Agriculture = 'agriculture';
    case TextileAndApparel = 'textile_apparel';
    case Automotive = 'automotive';
    case FinanceAndInsurance = 'finance_insurance';
    case HospitalityAndTourism = 'hospitality_tourism';
    case ProfessionalServices = 'professional_services';
    case MediaAndAdvertising = 'media_advertising';
    case Energy = 'energy';
    case Mining = 'mining';
    case Telecommunications = 'telecommunications';
    case Other = 'other';
}
