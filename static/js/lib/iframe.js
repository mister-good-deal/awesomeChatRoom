/**
 * Iframe module
 *
 * @module               lib/iframe
 *
 * Helper class to integrate iframe in a page without scroll barre and in full width / height well integrated on the
 * page
 */

define([
    'jquery',
    'module',
    'lodash',
    'domReady!'
], function ($, module, _) {
    'use strict';

    /**
     * Iframe object
     *
     * @constructor
     * @alias       module:lib/iframe
     * @param       {Object}       settings Overriden settings
     */
    var Iframe = function (settings) {
        var resizeThrottle = _.bind(_.throttle(this.resize, 100), this);

        this.settings       = $.extend(true, {}, this.settings, module.config(), settings);
        this.page           = $(this.settings.selectors.page);
        this.iframe         = $(this.settings.selectors.iframe);

        if (this.iframe.length > 0) {
            this.iframeContext  = this.iframe.get(0).contentWindow;
            this.resizeObserver = new MutationObserver(function (mutations) {
                mutations.forEach(function () {
                    resizeThrottle();
                });
            });

            if (_.isFunction(this.iframeContext.$)) {
                this.initEvents();
                this.updateIframeWidth();
                this.resize();
            }
        }
    };

    Iframe.prototype = {
        /*====================================================
        =            Object settings / properties            =
        ====================================================*/

        /**
         * Default settings
         */
        "settings": {},
        /**
         * The id of the setInterval function
         */
        "setIntervalId": "",
        /**
         * The current page displayed jQuery DOM element
         */
        "page": {},
        /**
         * The iframe jQuery DOM element
         */
        "iframe": {},
        /**
         * The iframe context "Windows" as pure javascript DOM element
         */
        "iframeContext": {},
        /**
         * Mutation observer that calls resize method on each mutation observed
         */
        "resizeObserver": {},
        /**
         * Options for resizeObserver
         */
        "resizeObserverConfig": {
            "childList" : true,
            "attributes": true,
            "subtree"   : true
        },

        /*=====  End of Object settings / properties  ======*/

        /*==============================
        =            Events            =
        ==============================*/

        /**
         * Initialize all the events
         *
         * @method     initEvents
         */
        initEvents: function () {
            $(window).on('resize', _.bind(_.throttle(this.updateIframeWidth, 100), this));

            this.resizeObserver.observe(
                this.iframeContext.$(this.settings.selectors.iframeHeightContainer).get(0),
                this.resizeObserverConfig
            );
        },

        /**
         * Destroy all the initialized events
         *
         * @method     destroyEvents
         */
        destroyEvents: function () {
            $(window).off('resize', _.bind(_.throttle(this.updateIframeWidth, 100), this));
            this.resizeObserver.disconnect();
        },

        /*=====  End of Events  ======*/

        /*=========================================
        =            Utilities methods            =
        =========================================*/

        /**
         * Resize the iframe and page height to fit well. If the iframe DOM element is removed, it stops the setIterval
         * and the page width watcher
         *
         * @method     resize
         */
        resize: function () {
            var iframeHeight;

            this.iframe = $(this.settings.selectors.iframe);

            if (this.iframe.length === 0) {
                this.destroyEvents();
            } else {
                iframeHeight = this.getIframeHeight();

                if (iframeHeight !== this.page.outerHeight()) {
                    console.log('resize');
                    this.setPageHeight(iframeHeight);
                }
            }
        },

        /**
         * Set the page height
         *
         * @method     setPageHeight
         * @param      {Number}  height  The page height with margin and padding
         */
        setPageHeight: function (height) {
            this.page.outerHeight(height);
            this.iframe.outerHeight(height);
        },

        /**
         * Get the iframe height
         *
         * @method     getIframeHeight
         * @return     {Number}  The iframe height with margin and padding
         */
        getIframeHeight: function () {
            return this.iframeContext.$(this.settings.selectors.iframeHeightContainer).outerHeight();
        },

        /**
         * Update the iframe width relative to page width
         *
         * @method     updateIframeWidth
         */
        updateIframeWidth: function () {
            var width = this.page.outerWidth();

            this.iframeContext.$(this.settings.selectors.iframeWidthContainer).outerWidth(width);
            this.iframe.outerWidth(width);
        }

        /*=====  End of Utilities methods  ======*/
    };

    return Iframe;
});
