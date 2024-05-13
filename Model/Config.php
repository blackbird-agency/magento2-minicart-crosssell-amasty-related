<?php
declare(strict_types = 1);

namespace Blackbird\MinicartCrosssell\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Config implements ArgumentInterface
{
    public const IS_MINICART_CROSSSELL_ENABLE = 'minicart_crosssell/general/minicart_crosssell_enable';
    public const MINICART_CROSSSELL_TITLE = 'minicart_crosssell/general/minicart_crosssell_title';
    public const MAX_NUMBER_MINICART_CROSSSELL_PRODUCTS = 'minicart_crosssell/general/minicart_crosssell_display';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
    ) {}


    /**
     * @param string|int|null $scopeCode
     * @param string          $scope
     *
     * @return bool
     */
    public function isMinicartCrosssellEnable(string|int $scopeCode = null, string $scope = ScopeInterface::SCOPE_STORE):string
    {
        return $this->scopeConfig->getValue(self::IS_MINICART_CROSSSELL_ENABLE, $scope, $scopeCode);
    }

    /**
     * @param string|int|null $scopeCode
     * @param string          $scope
     *
     * @return string
     */
    public function getMinicartCrosssellTitle(string|int $scopeCode = null, string $scope = ScopeInterface::SCOPE_STORE):string
    {
        return $this->scopeConfig->getValue(self::MINICART_CROSSSELL_TITLE, $scope, $scopeCode) ?? '';
    }

    /**
     * @param string|int|null $scopeCode
     * @param string          $scope
     *
     * @return int
     */
    public function getMaxNumberProductToDisplay(string|int $scopeCode = null, string $scope = ScopeInterface::SCOPE_STORE):int
    {
        return (int) $this->scopeConfig->getValue(self::MAX_NUMBER_MINICART_CROSSSELL_PRODUCTS, $scope, $scopeCode) ?? 0;
    }
}
