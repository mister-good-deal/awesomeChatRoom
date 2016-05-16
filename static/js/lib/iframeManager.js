/**
 * IframeManager module
 *
 * Helper class to integrate iframe in a page without scroll barre and in full width / height well integrated on the
 * page
 *
 * @module iframe
 */
define([
    'jquery',
    'module',
    'lodash',
    'domReady!'
], function ($, module, _) {
    'use strict';

    /**
     * IframeManager module
     *
     * Helper class to integrate iframe in a page without scroll barre and in full width / height well integrated on the
     * page
     *
     * @exports     iframeManager
     *
     * @param      {Navigation} Navigation  A Navigation Object
     * @param      {Object}     settings    Overriden settings
     *
     * @todo       Put iframe, iframeContext, resizeObserver, resizeObserverConfig out of the protype to handle multiple
     *             iframes at the same time
     *
     * @constructor
     * @alias      module:iframeManager
     */
    var IframeManager = function (Navigation, settings) {
        this.settings   = $.extend(true, {}, this.settings, module.config(), settings);
        this.navigation = Navigation;
    };

    IframeManager.prototype = {
        /*====================================================
        =            Object settings / properties            =
        ====================================================*/

        /**
         * Default settings
         */
        "settings": {},
        /**
         * The navigation object
         */
        "navigation": {},
        /**
         * The iframe jQuery DOM element
         */
        "iframe": {},
        /**
         * The iframe context "Windows" as pure javascript DOM element
         */
        "iframeContext": {},
        /**
         * The resize setIntervall ID
         */
        "resizeSetInterval": null,

        /*=====  End of Object settings / properties  ======*/

        /*==============================
        =            Events            =
        ==============================*/

        /**
         * Init an iframe
         *
         * @method     init
         * @param      {String}  iframeId  The iframe ID DOM selector
         */
        init: function (iframeId) {
            this.iframe         = $(iframeId);
            this.iframeContext  = this.iframe.get(0).contentDocument;

            $('body', this.iframeContext).css({
                "margin" : 0,
                "padding": 0,
                "height" : "100%"
            });

            this.setPageHeight($('body').innerHeight);
            this.destroyEvents();
            this.initEvents();
            this.updateIframeWidth();
        },

        /**
         * Initialize all the events
         *
         * @method     initEvents
         */
        initEvents: function () {
            $(window).on('resize', _.bind(_.throttle(this.updateIframeWidth, 100), this));
            this.resizeSetInterval = setInterval(_.bind(this.resize, this), this.settings.resizeInterval);
        },

        /**
         * Destroy all the initialized events
         *
         * @method     destroyEvents
         */
        destroyEvents: function () {
            $(window).off('resize', _.bind(_.throttle(this.updateIframeWidth, 100), this));
            clearInterval(this.resizeSetInterval);
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

            if (this.iframe.length === 0) {
                this.destroyEvents();
            } else {
                iframeHeight = this.getIframeHeight();

                if (iframeHeight !== this.navigation.getPage().outerHeight()) {
                    this.setPageHeight(iframeHeight);
                }

                this.updateIframeWidth();
            }
        },

        /**
         * Set the page height
         *
         * @method     setPageHeight
         * @param      {Number}  height  The page height with margin and padding
         */
        setPageHeight: function (height) {
            this.navigation.getPage().outerHeight(height);
            this.iframe.outerHeight(height);
        },

        /**
         * Get the iframe height
         *
         * @method     getIframeHeight
         * @return     {Number}  The iframe height with margin and padding
         */
        getIframeHeight: function () {
            return $(this.settings.selectors.iframeHeightContainer, this.iframeContext).outerHeight();
        },

        /**
         * Update the iframe width relative to page width
         *
         * @method     updateIframeWidth
         */
        updateIframeWidth: function () {
            var width = this.navigation.getPage().outerWidth();

            $(this.settings.selectors.iframeWidthContainer, this.iframeContext).outerWidth(width);
            this.iframe.outerWidth(width);
        },

        /**
         * Append the kibana iframe if it is not already present on the page
         *
         * @method     loadKibanaIframe
         */
        loadKibanaIframe: function () {
            var self = this,
                iframe;

            if ($(this.settings.selectors.kibanaIframe).length === 0) {
                $.getJSON('kibana/getIframe', function (data) {
                    iframe = $('<iframe/>', {
                        "src"        : data.src,
                        "id"         : self.settings.selectors.kibanaIframe.substr(1),
                        "frameBorder": 0,
                        "seamless"   : "seamless",
                        "load"       : _.bind(self.init, self, self.settings.selectors.kibanaIframe)
                    });

                    $('.page[data-url="kibana"]').append(iframe);
                });
            }
        }

        /*=====  End of Utilities methods  ======*/
    };

    return IframeManager;
});
