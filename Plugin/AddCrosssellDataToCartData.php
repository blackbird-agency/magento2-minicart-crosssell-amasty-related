<?php

namespace Blackbird\MinicartCrosssell\Plugin;

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
use Blackbird\MinicartCrosssell\Service\CrosssellRetriever;
use Blackbird\MinicartCrosssell\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Element\Template;
use PHPUnit\Exception;
use Psr\Log\LoggerInterface;

class AddCrosssellDataToCartData
{

    const MEDIA_PATH = 'media/catalog/product';
    const TEMPLATE = 'Blackbird_MinicartCrosssell::addToCart.phtml';

    /**
     * @param CrosssellRetriever         $crossSellRetriever
     * @param Session                    $checkoutSession
     * @param CheckoutHelper             $checkoutHelper
     * @param ProductRepositoryInterface $productRepository
     * @param AttributeResource          $attributeResource
     * @param Configurable               $configurable
     * @param Config                     $config
     * @param StoreManagerInterface      $storeManager
     * @param Template                   $template
     * @param LoggerInterface            $logger
     * @param array                      $attributeValidators
     */
    public function __construct
    (
        protected CrosssellRetriever $crossSellRetriever,
        protected Session $checkoutSession,
        protected CheckoutHelper $checkoutHelper,
        protected ProductRepositoryInterface $productRepository,
        protected AttributeResource $attributeResource,
        protected Configurable $configurable,
        protected Config $config,
        protected StoreManagerInterface $storeManager,
        protected Template $template,
        protected LoggerInterface $logger,
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
                'items'       => $this->getCrossSellProductData(),
                'title'       => $this->config->getMinicartCrosssellTitle(),
                'max_product' => $this->config->getMaxNumberProductToDisplay()
            ];

            return $result;
        }
        catch (\Exception $e) {
            $this->logger->error($e);
            return [];
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

        $crosssellProductsArray = $this->crossSellRetriever->getCrossSells();
        $crosssellProducts = [];

        foreach ($crosssellProductsArray as $crosssellProduct) {
            $crosssellProducts[] = $this->getProductData($crosssellProduct);
        }

        return $crosssellProducts;
    }

    /**
     * @throws LocalizedException
     */
    public function getProductData(Product $crosssellProduct): array
    {
        $configurable = $this->getConfigurableProductFromSimple($crosssellProduct);
        $options = [];

        if(isset($configurable)) {
            $options =  $this->getCrosssellSimpleProductOption($configurable, $crosssellProduct->getSku());
        }

        return [
            'name' =>        $crosssellProduct->getData('name'),
            'option' =>      $options,
            'description' => $crosssellProduct->getData('description_vignette'),
            'old_price_html' =>   $this->checkoutHelper
                ->formatPrice($crosssellProduct->getData('price')),
            'old_price' =>   $crosssellProduct->getData('price'),
            'price_html' =>       $this->checkoutHelper
                ->formatPrice($crosssellProduct->getData('final_price')),
            'price' =>       $crosssellProduct->getData('final_price'),
            'image' =>       $this->getThumbnailUrl($crosssellProduct->getData('thumbnail')),
            'color' =>       $crosssellProduct->getData('code_couleur') ?? '',
            'button' =>      $this->getAddToCartButtonHtml($crosssellProduct, $configurable)
        ];
    }

    /**
     * @param Product $configurable
     * @param string  $simpleSku
     *
     * @return array
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

    /**
     * @param $simpleProduct
     * @return Product|null
     */
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

    /**
     * @param $simpleProduct
     * @return array
     */
    public function getConfigurableProductData($simpleProduct):array
    {
        try {
            $parentIds = $this->configurable->getParentIdsByChild($simpleProduct->getId());
            if (!empty($parentIds)) {
                $configurableProduct = $this->productRepository->getById($parentIds[0]);
                $superAttributes = [];
                foreach ($configurableProduct->getTypeInstance()->getConfigurableAttributes($configurableProduct) as $attribute) {
                    $attributeId = $attribute->getProductAttribute()->getId();
                    $superAttributes[$attributeId] = $simpleProduct->getData($attribute->getProductAttribute()->getAttributeCode());
                }
                return $superAttributes;
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        return [];
    }

    /**
     * @param $crosssellProduct
     * @param $configurableProduct
     *
     * @return string
     * @throws LocalizedException
     */
    public function getAddToCartButtonHtml($crosssellProduct, $configurableProduct): string
    {
        if (!isset($configurableProduct)){
            return '';
        }

        $configurableProductData = $this->getConfigurableProductData($crosssellProduct);
        $options = $configurableProductData;

        $formKey = $this->template->getLayout()
            ->createBlock(\Magento\Framework\View\Element\FormKey::class)->getFormKey();

        return $this->template->getLayout()->createBlock(Template::class)
            ->setTemplate(self::TEMPLATE)
            ->setData([
                'configurableProductId' => $configurableProduct->getId(),
                'options' => $options,
                'addToCartUrl' => $this->template->getUrl('checkout/cart/add'),
                'formKey' => $formKey,
            ])->toHtml();
    }

    /**
     * @param $fileName
     *
     * @return string
     */
    public function getThumbnailUrl($fileName):string
    {
        return $this->template->getBaseUrl() . self::MEDIA_PATH . $fileName;
    }
}
