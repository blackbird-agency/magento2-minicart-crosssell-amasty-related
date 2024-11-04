define([
    'jquery',
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'ko',
], function ($, Component, storage, ko) {

    "use strict";

    return Component.extend({

        /**
         * Initizalize
         */
        initialize: function() {
            const _self = this;
            _self._super();
        },

        /**
         * Return title of cross-sell part
         * @returns string
         */
        getCrosssellTitle: function() {
            return  storage.get('cart')().related_items.title;
        },

        /**
         * Enable cross-sell of related
         * @returns {*}
         */
        areRelatedItems: function() {
            if(!storage.get('cart')().related_items.items) {
                return false;
            }
            return storage.get('cart')().related_items.items.length;
        }
    });
});
