<?php

declare(strict_types = 1);

namespace Blackbird\MinicartCrosssell\Api;

interface AttributeValidatorInterface
{
    public function isValid(array $attribute): bool;
}
