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
            minicart: $('.block-minicart'),
            minicartCrosssell: $('.minicart-crosssell-items')
        },

        selectors: {
            slickInitialized: '.slick-initialized',
        },

        classes: {
            mobileHidden: 'mobile-hidden'
        },

        states: {
            visible: ':visible',
            isMobile: false,
        },

        events: {
            clickOnCartButton: 'click.cartButton',
            ajaxCrosssellUpdate: 'ajax:minicartCrossellUpdated',
            resize: 'resize'
        },

        data: {
            numberSlidesMobile: 1,
            numberSlidesTabletDesktop: 2,
            oldRelatedItems: '',
            mobileBreakpoint: 768
        },

        initialize: function() {
            this._super();
            const _self = this;

            _self.updateIsMobile();

            $(window).on(_self.events.resize, function () {
                _self.updateIsMobile();
                _self.resetSizing();
            });

            $(_self.components.cartButton).off(_self.events.clickOnCartButton)
                .on(_self.events.clickOnCartButton, function() {
                    _self.resetSizing();
                })

            _self.updateRelatedItems();

            customerData.get('cart').subscribe(function() {
                _self.updateRelatedItems(customerData.get('cart')().related_items.items);
            });
        },

        updateIsMobile: function() {
            const _self = this;

            const isMobile = $(window).innerWidth() <= _self.data.mobileBreakpoint;
            _self.states.isMobile = isMobile;
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
            _self.resetCrosssellCarrousel();
            _self.initCarrousel();
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
         * Reset the size of the slides from carrousel
         */
        resetSizing: function() {
            let minicartCrosssellCarrousel = $('.minicart-crosssell-items');

            minicartCrosssellCarrousel.slick('setPosition');
        },

        /**
         * Init cross-sell Carrousel
         */
        initCarrousel:function() {
            const _self = this;
            const minicartCrosssellCarrousel = $('.minicart-crosssell-items');

            minicartCrosssellCarrousel.not(_self.selectors.slickInitialized).slick({
                accessibility: true,
                dots: false,
                autoplay: false,
                arrows: false,
                vertical: true,
                swipe: true,
                infinite: false,
                verticalSwiping: true,
                touchThreshold: 10,
                slidesToShow: _self.data.numberSlidesTabletDesktop,
                responsive: [
                    {
                        breakpoint: _self.data.mobileBreakpoint,
                        settings: {
                            swipe: true,
                            touchThreshold: 20,
                            vertical: false,
                            slidesToShow: _self.data.numberSlidesMobile,
                            dots: true,
                            arrows: false,
                        }
                    }
                ]
            }).show();
            minicartCrosssellCarrousel.removeClass(_self.classes.mobileHidden);
        },

        /**
         * Reset Carrousel
         */
        resetCrosssellCarrousel: function() {
            const _self = this;
            const minicartCrosssell = $('.minicart-crosssell-items');

            minicartCrosssell.addClass(_self.classes.mobileHidden);
            minicartCrosssell.empty();
            minicartCrosssell.slick('unslick');
        }
    });
});
