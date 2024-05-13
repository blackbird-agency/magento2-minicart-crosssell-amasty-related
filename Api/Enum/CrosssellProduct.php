<?php

declare(strict_types=1);

namespace Blackbird\MinicartCrosssell\Api\Enum;

enum CrosssellProduct: string
{
    case PRODUCT_TYPE_SIMPLE = 'simple';
    case PRODUCT_TYPE_CONFIGURABLE = 'configurable';
    case ENTITY_ID = 'entity_id';

}
