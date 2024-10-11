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
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollectionFactory;

class CrosssellRetriever
{
    /**
     * @param GroupRepositoryInterface $groupRepositoryInterface
     * @param ProductProvider          $productProvider
     * @param ConfigProvider           $configProvider
     * @param Session                  $session
     * @param GroupRepository          $groupRepository
     * @param Visibility               $productVisibility
     * @param Stock                    $stockHelper
     * @param Wrapper                  $wrapper
     * @param Config                   $config
     */
    public function __construct(
        protected GroupRepositoryInterface $groupRepositoryInterface,
        protected ProductProvider $productProvider,
        protected ConfigProvider $configProvider,
        protected Session $session,
        protected GroupRepository $groupRepository,
        protected Visibility $productVisibility,
        protected Stock $stockHelper,
        protected Wrapper $wrapper,
        protected Config $config,
        protected QuoteItemCollectionFactory $quoteItemCollectionFactory,
    ) {
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function getCrossSells(): array
    {
        $entity = $this->getLastAddedProductInCart(CrosssellProduct::PRODUCT_TYPE_SIMPLE->value);

        if(!($entity)) {
            return [];
        }

        try {
            return $this->getCrossSellProductCollection($entity);
        } catch (NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * @param Product $entity
     * @param int     $shift
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCrossSellProductCollection(Product $entity, int $shift = 0): array
    {
        $entityId           = (int) $entity->getId();
        $productsCollection = [];
        $maxNumberProductToDisplay = $this->config->getMaxNumberProductToDisplay();

        while (\count($productsCollection) < $maxNumberProductToDisplay && $currentGroup = $this->getCurrentGroup($entityId, $shift)) {
            $currentGroupProduct = $this->getProductsFromRules($currentGroup, $entity, $productsCollection, $entityId);
            foreach($currentGroupProduct as $currentProduct) {
                $productsCollection[] = $currentProduct->setDoNotUseCategoryId(true);
                if(\count($productsCollection) === $maxNumberProductToDisplay) {
                    break 2;
                }
            }

            if (!$this->configProvider->isEnabledSubsequentRules()) {
                break;
            }

            $shift++;
        }

        return $productsCollection;
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
            $productId = $entity instanceof ProductInterface ? (int) $entityId : null;

            return $this->prepareCollection($group, $productCollection, $productId)->load();
        } catch (NoCandidateFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $type
     *
     * @return Product|null
     * @throws LocalizedException
     */
    public function getLastAddedProductInCart(string $type): ?Product
    {
        try {
            $quote = $this->session->getQuote();
            $collection = $this->quoteItemCollectionFactory->create();
            $collection->setQuote($quote);
            $collection->addOrder('created_at', 'DESC')->getItems();

            foreach ($collection as $item) {
                if(($type === CrosssellProduct::PRODUCT_TYPE_CONFIGURABLE->value) && $item->getParentItemId() === null) {
                    return $item->getProduct();
                }

                if(($type === CrosssellProduct::PRODUCT_TYPE_SIMPLE->value) && $item->getParentItemId() !== null) {
                    return $item->getProduct();
                }
            }

            return null;

        } catch (NoSuchEntityException $e) {
            return null;
        }
    }


    /**
     * @param int $entityId
     * @param int $shift
     *
     * @return GroupInterface|bool
     */
    private function getCurrentGroup(int $entityId, int $shift = 0): GroupInterface|bool
    {
        return $this->groupRepositoryInterface->getGroupByIdAndPosition(
            $entityId, BlockPosition::MINICART->value, $shift);
    }

    /**
     * @param Group      $group
     * @param Collection $collection
     * @param int|null   $productId
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
}
