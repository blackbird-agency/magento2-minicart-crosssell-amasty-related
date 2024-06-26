define([
    'jquery',
    'ko',
    'Magento_Customer/js/customer-data',
    'uiComponent',
    'slick'
], function($, ko, customerData, Component) {
    'use strict';

    return Component.extend({
        relatedItems: ko.observableArray([]),
        previousRelatedItems: ko.observableArray([]),

        components: {
            cartButton: $('.action.showcart'),
        },

        selectors: {
            slickInitialized: '.slick-initialized',
        },

        events: {
            clickOnCartButton: 'click.cartButton'
        },

        data: {
            numberSlidesMobile: 1,
            numberSlidesTabletDesktop: 2,
            oldRelatedItems: '',
        },

        initialize: function() {
            this._super();
            const _self = this;

            _self.updateRelatedItems();
            customerData.get('cart').subscribe(function() {
                _self.updateRelatedItems(customerData.get('cart')().related_items.items);
            });
        },

        /**
         * Update elements
         * @returns void
         */
        updateRelatedItems: function() {

            const _self = this;
            let cart = customerData.get('cart');

            if (cart().related_items && cart().related_items.items) {
                let newRelatedItems = cart().related_items.items;
                let previousItemsJSON = _self.previousRelatedItems() ?
                    JSON.stringify(ko.toJS(_self.previousRelatedItems)) : null;
                let newItemsJSON = JSON.stringify(newRelatedItems);

                if (previousItemsJSON !== newItemsJSON) {
                    if (newRelatedItems.length > 0) {
                        _self.relatedItems.removeAll();
                        _self.relatedItems(newRelatedItems);
                        _self.previousRelatedItems(newRelatedItems);
                    } else {
                        _self.relatedItems.removeAll();
                        _self.relatedItems(_self.previousRelatedItems());
                    }
                } else if (!newRelatedItems.length) {
                    _self.relatedItems.removeAll();
                    _self.relatedItems(_self.previousRelatedItems());
                }
            } else {
                _self.relatedItems.removeAll();
                _self.relatedItems(_self.previousRelatedItems());
            }
        },

        /**
         * Return html for display options
         * @param item
         * @returns {string}
         */
        getOptionValue: function(item) {

            let htmlOptions = '';

            $.each(item.option, function (i) {
                $.each(item.option[i], function (name, value) {
                    htmlOptions += `<span class="option-${name}">${value}</span>`;
                });
            });

            return htmlOptions;
        },

        /**
         *
         * @param price
         * @param oldPrice
         * @returns {number}
         */
        getDiscountAmount: function(price, oldPrice) {

            let discount = ((oldPrice - price) / oldPrice) * 100;
            return Math.floor(discount);
        },

        /**
         * Init cross-sell Carrousel
         */
        initCarrousel:function() {
            const _self = this;

            let minicartCrosssellCarrousel = $('.minicart-crosssell-items');

            _self.components.cartButton.off(_self.events.clickOnCartButton)
                .on(_self.events.clickOnCartButton, function() {
                    minicartCrosssellCarrousel.not(_self.selectors.slickInitialized).slick({
                        accessibility: true,
                        dots: false,
                        autoplay: false,
                        arrows: false,
                        vertical: true,
                        verticalSwiping: true,
                        slidesToShow: _self.data.numberSlidesTabletDesktop,
                        slidesToScroll: _self.data.numberSlidesMobile,
                        responsive: [
                            {
                                breakpoint: 1024,
                                settings: {
                                    vertical: false,
                                    slidesToShow: _self.data.numberSlidesMobile,
                                    slidesToScroll: _self.data.numberSlidesMobile,
                                    dots: true,
                                    arrows: false,
                                }
                            }
                        ]
                    }).show();
                })
        },
    });
});
