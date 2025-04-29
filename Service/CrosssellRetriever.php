<?php
declare(strict_types=1);

namespace Blackbird\MinicartCrosssell\Service;

use Amasty\Mostviewed\Api\Data\GroupInterface;
use Amasty\Mostviewed\Api\GroupRepositoryInterface;
use Amasty\Mostviewed\Model\ConfigProvider;
use Amasty\Mostviewed\Model\Di\Wrapper;
use Amasty\Mostviewed\Model\Group;
use Amasty\Mostviewed\Model\OptionSource\Sortby;
use Amasty\Mostviewed\Model\ProductProvider;
use Amasty\Mostviewed\Model\Repository\GroupRepository;
use Amasty\Mostviewed\Model\ResourceModel\Product\Collection;
use Http\Discovery\Exception\NoCandidateFoundException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Checkout\Model\Session;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Blackbird\MinicartCrosssell\Api\Enum\BlockPosition;
use Blackbird\MinicartCrosssell\Api\Enum\CrosssellProduct;
use Blackbird\MinicartCrosssell\Model\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableType;
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollectionFactory;

class CrosssellRetriever
{
    /**
     * @var Product[]
     */
    protected $lastAddedProductInCartByType = [];
    
    /**
     * @param GroupRepositoryInterface $groupRepositoryInterface
     * @param ProductProvider $productProvider
     * @param ConfigProvider $configProvider
     * @param Session $session
     * @param GroupRepository $groupRepository
     * @param Visibility $productVisibility
     * @param Stock $stockHelper
     * @param Wrapper $wrapper
     * @param Config $config
     * @param QuoteItemCollectionFactory $quoteItemCollectionFactory
     * @param ConfigurableType $configurableType
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        protected GroupRepositoryInterface   $groupRepositoryInterface,
        protected ProductProvider            $productProvider,
        protected ConfigProvider             $configProvider,
        protected Session                    $session,
        protected GroupRepository            $groupRepository,
        protected Visibility                 $productVisibility,
        protected Stock                      $stockHelper,
        protected Wrapper                    $wrapper,
        protected Config                     $config,
        protected QuoteItemCollectionFactory $quoteItemCollectionFactory,
        protected ConfigurableType           $configurableType,
        protected ProductRepositoryInterface $productRepository
    )
    {
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function getCrossSells(): array
    {
        try {
            $entity = $this->getLastAddedProductInCart(CrosssellProduct::PRODUCT_TYPE_SIMPLE->value);
            
            return $this->getCrossSellProductCollection($entity);
        } catch (LocalizedException $e) {
            return [];
        }
    }

    /**
     * @param Product $entity
     * @param int $shift
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCrossSellProductCollection(Product $entity, int $shift = 0): array
    {
        $entityId = (int)$entity->getId();
        $productsCollection = [];
        $addedProductIds = [];
        $maxNumberProductToDisplay = $this->config->getMaxNumberProductToDisplay();
        $maxIterations = 30;
        $iterationsCount = 0;
        $totalMaxProductsAllowed = 0;

        while (\count($productsCollection) < $maxNumberProductToDisplay && $currentGroup = $this->getCurrentGroup($entityId, $shift)) {
            if ($iterationsCount >= $maxIterations) {
                break;
            }

            $iterationsCount++;
            $totalMaxProductsAllowed += $currentGroup->getMaxProducts();
            $effectiveMaxProducts = min($maxNumberProductToDisplay, $totalMaxProductsAllowed);
            $subIterationsCount = 0;

            do {
                if ($subIterationsCount >= $maxIterations) {
                    break;
                }
                $subIterationsCount++;

                $currentGroupProduct = $this->getProductsFromRules($currentGroup, $entity, $productsCollection, $entityId);

                foreach ($currentGroupProduct as $currentProduct) {
                    $productId = $currentProduct->getId();

                    if ($this->shouldSkipProductById($currentProduct, $addedProductIds)) {
                        continue;
                    }

                    $productsCollection[] = $currentProduct->setDoNotUseCategoryId(true);
                    $addedProductIds[] = $productId;

                    if (\count($productsCollection) === $effectiveMaxProducts) {
                        break 2;
                    }
                }
            } while ($currentGroup->getMaxProducts() > 1 && \count($currentGroupProduct) > 0);

            if (!$this->configProvider->isEnabledSubsequentRules()) {
                break;
            }

            $shift++;
        }

        return $productsCollection;
    }

    /**
     * @param $currentProduct
     * @param array $addedProductIds
     * @return bool
     * @throws NoSuchEntityException
     */
    private function shouldSkipProductById($currentProduct, array $addedProductIds): bool
    {
        if (!$currentProduct->isInStock()) {
            return true;
        }

        $configurableProduct = $this->getConfigurableProduct($currentProduct->getId());
        if ($configurableProduct && !$configurableProduct->isInStock()) {
            return true;
        }

        if (in_array($currentProduct->getId(), $addedProductIds, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param $group
     * @param $entity
     * @param $productsCollection
     * @param $entityId
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection|Collection
     * @throws NoSuchEntityException
     */
    public function getProductsFromRules($group, $entity, $productsCollection, $entityId): ?Collection
    {
        try {
            $productCollection = $this->productProvider->getAppliedProducts($group, $entity);
            $productCollection->setPageSize($group->getMaxProducts());
            $productId = $entity instanceof ProductInterface ? (int)$entityId : null;

            return $this->prepareCollection($group, $productCollection, $productId)->load();
        } catch (NoCandidateFoundException $e) {
            return null;
        }
    }

    /**
     * @throws LocalizedException|NoSuchEntityException
     */
    public function getLastAddedProductInCart(string $type): Product
    {
        if (isset($this->lastAddedProductInCartByType[$type])) {
            return $this->lastAddedProductInCartByType[$type];
        }
        
        $quote = $this->session->getQuote();
        $collection = $this->quoteItemCollectionFactory->create();
        $collection->setQuote($quote);
        $collection->addOrder('created_at', 'DESC')->getItems();

        foreach ($collection as $item) {
            if (($type === CrosssellProduct::PRODUCT_TYPE_CONFIGURABLE->value) && $item->getParentItemId() === null) {
                $this->lastAddedProductInCartByType[$type] = $item->getProduct();
                return $this->lastAddedProductInCartByType[$type];
            }

            if (($type === CrosssellProduct::PRODUCT_TYPE_SIMPLE->value) && $item->getParentItemId() !== null) {
                $this->lastAddedProductInCartByType[$type] = $item->getProduct();
                return $this->lastAddedProductInCartByType[$type];
            }
        }

        throw new NoSuchEntityException(__('There are no last added products in cart matching crossell conditions.'));
    }


    /**
     * @param int $entityId
     * @param int $shift
     *
     * @return GroupInterface|bool
     */
    public function getCurrentGroup(int $entityId, int $shift = 0): GroupInterface|bool
    {
        return $this->groupRepositoryInterface->getGroupByIdAndPosition(
            $entityId, BlockPosition::MINICART->value, $shift);
    }

    /**
     * @param Group $group
     * @param Collection $collection
     * @param int|null $productId
     *
     * @return Collection
     */
    private function prepareCollection(Group $group, Collection $collection, ?int $productId = null): Collection
    {
        $collection
            ->addAttributeToSelect(['*'])
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite();

        $collection->addFieldToFilter('type_id', ['neq' => \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE]);
        $this->applySorting($group->getSorting(), $collection);

        if ($productId) {
            $collection->addIdFilter($productId, true);
        }

        return $collection;
    }

    /**
     * @param            $sorting
     * @param Collection $collection
     *
     * @return void
     */
    private function applySorting($sorting, Collection $collection): void
    {
        $dir = Select::SQL_ASC;
        switch ($sorting) {
            case Sortby::NAME:
                $sortAttr = 'name';
                break;
            case Sortby::PRICE_ASC:
                $sortAttr = 'price';
                break;
            case Sortby::PRICE_DESC:
                $sortAttr = 'price';
                $dir = Select::SQL_DESC;
                break;
            case Sortby::NEWEST:
                $sortAttr = 'created_at';
                $dir = Select::SQL_DESC;
                break;
            case Sortby::BESTSELLERS:
            case Sortby::MOST_VIEWED:
            case Sortby::REVIEWS_COUNT:
            case Sortby::TOP_RATED:
                if ($this->wrapper->isAvailable()) {
                    $method = $this->wrapper->getMethodByCode($sorting);
                    $method->apply($collection, Select::SQL_DESC);
                    $sortAttr = $sorting;
                    $dir = Select::SQL_DESC;
                } else {
                    $sortAttr = null;
                }

                break;
            default:
                $sortAttr = null;
        }

        if ($sortAttr === null) {
            $collection->getSelect()->orderRand()->limit(3);
        } else {
            $collection->setOrder($sortAttr, $dir);
        }

        $collection->setOrder(CrosssellProduct::ENTITY_ID->value, Select::SQL_ASC);
    }

    /**
     * @param string $productId
     * @return ProductInterface|null
     * @throws NoSuchEntityException
     */
    private function getConfigurableProduct(string $productId): ?ProductInterface
    {
        $parentIds = $this->configurableType->getParentIdsByChild($productId);

        if (!empty($parentIds)) {
            return $this->productRepository->getById(\current($parentIds));
        }

        return null;
    }
}
