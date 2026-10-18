/**
 * Webtech Solutions
 * Copyright © 2026 Webtech Solutions. All rights reserved.
 */
define([
    'uiComponent'
], function (Component) {
    'use strict';

    var notices = window.checkoutConfig.tracelogicItemNotices || {};

    return Component.extend({
        defaults: {
            template: 'WebtechSolutions_TraceLogic/summary/item/warning'
        },

        /**
         * @param {Object} quoteItem
         * @return {Object|null}
         */
        getNotice: function (quoteItem) {
            return notices[quoteItem['item_id']] || null;
        },

        /**
         * Fixed positioning keeps the tooltip from being clipped by the scrollable summary list.
         *
         * @param {Object} data
         * @param {Event} event
         */
        showTip: function (data, event) {
            var wrap = event.currentTarget,
                tip = wrap.querySelector('.tracelogic-item-warning-tip'),
                rect = wrap.getBoundingClientRect(),
                width = Math.min(280, window.innerWidth - 16);

            tip.style.width = width + 'px';
            tip.style.top = rect.bottom + 4 + 'px';
            tip.style.left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8)) + 'px';
        },

        /**
         * @param {Object} quoteItem
         * @return {String}
         */
        getText: function (quoteItem) {
            var notice = this.getNotice(quoteItem);

            return notice ? notice.reasons.join('\n') : '';
        }
    });
});
