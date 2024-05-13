<?php
declare(strict_types = 1);

namespace Blackbird\MinicartCrosssell\Plugin;

use Amasty\Mostviewed\Model\OptionSource\BlockPosition;
use Blackbird\MinicartCrosssell\Api\Enum\BlockPosition as MinicartBlockPosition;

class AddMinicartBlockPosition
{
    /**
     * @param BlockPosition $subject
     * @param array         $result
     *
     * @return array
     */
    public function afterToOptionArray(BlockPosition $subject, array $result): array
    {
        $result[] = ['value' => MinicartBlockPosition::MINICART, 'label' => __('Minicart Position')];

        return $result;
    }
}
