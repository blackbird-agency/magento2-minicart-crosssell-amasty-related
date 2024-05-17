define([
    'jquery',
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/modal/alert',
    'ko',
    'mage/translate',
    'slick',
], function ($, Component, storage, alert, ko) {

    "use strict";

    return Component.extend({

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
        },

        cart: storage.get('cart'),

        /**
         * Initizalize
         */
        initialize: function() {
            const _self = this;
            _self._super();
            _self.updateRelatedItems();
            this.cart.subscribe(_self.updateRelatedItems);
        },

        /**
         * Update Data from Related Items
         * @returns array
         */
        updateRelatedItems: function() {
            this.relatedItems = ko.observable(0);
            let cart = storage.get('cart');

            if (cart().related_items) {
                return this.relatedItems(cart().related_items.items);
            } else {
                return this.relatedItems(0);
            }
        },

        /**
         * Return title of cross-sell part
         * @returns string
         */
        getCrosssellTitle: function() {
            let cart = storage.get('cart');
            return cart().related_items.title;
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
                        slidesToScroll: _self.data.numberSlidesTabletDesktop,
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
        }
    });
});
