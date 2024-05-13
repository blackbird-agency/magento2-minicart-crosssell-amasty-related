<?php

namespace Blackbird\MinicartCrosssell\Plugin;

use Bultex\Theme\Api\Service\ProductAttributeProviderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeResource;
use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Session;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Blackbird\MinicartCrosssell\Api\AttributeValidatorInterface;
use Blackbird\MinicartCrosssell\Service\CrossSellRetriever;
use Blackbird\MinicartCrosssell\Model\Config;

class AddCrosssellDataToCartData
{
    /**
     * @param CrossSellRetriever                $crossSellRetriever
     * @param Session                           $checkoutSession
     * @param CheckoutHelper                    $checkoutHelper
     * @param ProductRepositoryInterface        $productRepository
     * @param ProductAttributeProviderInterface $productAttributeProvider
     * @param AttributeResource                 $attributeResource
     * @param Configurable                      $configurable
     * @param Config                            $config
     * @param AttributeValidatorInterface[]     $attributeValidators
     */
    public function __construct
    (
        protected CrossSellRetriever $crossSellRetriever,
        protected Session $checkoutSession,
        protected CheckoutHelper $checkoutHelper,
        protected ProductRepositoryInterface $productRepository,
        protected ProductAttributeProviderInterface $productAttributeProvider,
        protected AttributeResource $attributeResource,
        protected Configurable $configurable,
        protected Config $config,
        protected array $attributeValidators = []
    ) {
    }

    /**
     * @param Cart $subject
     * @param      $result
     *
     * @return array
     */
    public function afterGetSectionData(Cart $subject, $result): array
    {
        try {
            $result['related_items'] = [
                'items' => $this->getCrossSellProductData(),
                'title' => $this->config->getMinicartCrosssellTitle(),
                'max_product' =>$this->config->getMaxNumberProductToDisplay()
            ];
            return $result;
        } catch (LocalizedException $e) {
            return $result;
        }
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function getCrossSellProductData(): array
    {
        if ($this->config->isMinicartCrosssellEnable() !== '1') {
            return [];
        }

        try {
            $crosssellProductsArray = $this->crossSellRetriever->getCrossSells();

            $crosssellProducts = [];

            foreach ($crosssellProductsArray as $crosssellProduct) {

                $configurable = $this->getConfigurableProductFromSimple($crosssellProduct);

                $familyName = $this->productAttributeProvider->getFamilleAttributeBySku(
                    $configurable->getSku()) ?? '';

                $crosssellProducts[] = [
                    'name' =>        $crosssellProduct->getData('name'),
                    'option' =>      $this->getCrosssellSimpleProductOption($configurable, $crosssellProduct->getSku()),
                    'category' =>    $familyName,
                    'description' => $crosssellProduct->getData('description_vignette'),
                    'old_price' =>   $this->checkoutHelper
                        ->formatPrice($crosssellProduct->getData('price')),
                    'price' =>       $this->checkoutHelper
                        ->formatPrice($crosssellProduct->getData('final_price')),
                    'image' =>       $crosssellProduct->getData('thumbnail'),
                ];
            }

            return $crosssellProducts;
        }
        catch (NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * @param Product $configurable
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCrosssellSimpleProductOption(Product $configurable, string $simpleSku):array
    {
        $crosssellProductOption = [];
        $attributes = [];

        $configurableOptions = $configurable->getTypeInstance()->getConfigurableOptions($configurable);

        foreach($configurableOptions as $options)
        {
            foreach($options as $option)
            {
                if($option['sku'] === $simpleSku)
                {
                    $attributes[] = $option;
                }
            }
        }

        foreach ($attributes as $attribute)
        {
            foreach ($this->attributeValidators as $attributeValidator) {
                if (!$attributeValidator->isValid($attribute)) {
                    continue 2;
                }
            }

            $crosssellProductOption[] = [
                $attribute['attribute_code'] => $attribute['default_title']
            ];
        }

        return $crosssellProductOption;
    }

    public function getConfigurableProductFromSimple($simpleProduct): ?Product
    {
        try {
            $parentIds = $this->configurable->getParentIdsByChild($simpleProduct->getId());

            if (empty($parentIds)) {
                return null;
            }

            $parentId = reset($parentIds);

            return $this->productRepository->getById($parentId);

        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
