<?php
/**
 * @author 18th DigiTech Team
 * @copyright Copyright (c) 2020 18th DigiTech (https://www.18thdigitech.com)
 * @package Eighteentech_Givex
 */
declare(strict_types=1);

namespace Eighteentech\Givex\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * CardTypes
 */
class ResultStatus implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        foreach ($this->getOptions() as $value => $label) {
            $result[] = [
                 'value' => $value,
                 'label' => $label,
             ];
        }

        return $result;
    }

    public function getOptions()
    {
        return [
            0 => __('Pending'),
            1 => __('Completed'),
        ];
    }
}
