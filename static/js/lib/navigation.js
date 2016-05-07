/**
 * Navigation module
 *
 * Handle the current page attributes such as the page title or the page parameters.
 * It loads pages by hide / show pages DOM classes elements to use a Single Page Web pattern and avoiding browser
 * page reloading to keep the WebSocket connection alive.
 * It load pages on hashchange event and detect the page to load by parsing the hash.
 *
 * @module               lib/navigation
 */

define([
    'jquery',
    'module',
    'lodash'
], function ($, module, _) {
    'use strict';

    /**
     * Navigation object
     *
     * @constructor
     * @alias       module:lib/navigation
     * @param       {Object}       settings Overriden settings
     */
    var Navigation = function (settings) {
        this.settings = $.extend(true, {}, this.settings, module.config(), settings);
        this.initEvents();
    };

    Navigation.prototype = {
        /*====================================================
        =            Object settings / properties            =
        ====================================================*/

        /**
         * Default settings
         */
        "settings" : {},
        /**
         * Current jQuery DOM element page
         */
        "page": {},
        /**
         * Current page URL (hash URL)
         */
        "pageUrl": "",
        /**
         * Current page Title
         */
        "pageTitle": "",
        /**
         * Current page parameters
         */
        "pageParameters": {},
        /**
         * Callbacks method to run actions on specific pages on loaded
         */
        "callbacks": {},

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
            $(window).on('hashchange', $.proxy(this.loadPageFromHash, this));
            $('body').on('click', this.settings.selectors.pageLink, $.proxy(this.openLink, this));
        },

        /**
         * Load a page when the page hash changes
         *
         * @method     loadPageFromHash
         * @return     {Boolean}  True if a page was loaded else false
         */
        loadPageFromHash: function () {
            var hash       = location.hash,
                pageUrl    = _.trimStart(_.head(_.split(hash, '?')), this.settings.urlPrefix),
                pageLoaded = false;

            if (_.startsWith(hash, this.settings.urlPrefix) && this.pageUrl !== pageUrl) {
                this.loadPageParameters(hash);
                this.loadPage(pageUrl);
                pageLoaded = true;
            }

            return pageLoaded;
        },

        /**
         * Display the page called on a page-link click
         *
         * @method     openLink
         * @param      {Event}  e       The event fired
         */
        openLink: function (e) {
            var url = $(e.currentTarget).attr('href') || $(e.currentTarget).attr('data-url');

            e.preventDefault();

            if (location.hash !== url) {
                this.loadPage(url);
            }
        },

        /*=====  End of Events  ======*/

        /*===============================
        =            Getters            =
        ===============================*/

        getPage: function () {
            return this.page;
        },

        /**
         * Get the current page URL (hash URL)
         *
         * @method     getPageUrl
         * @return     {String}  The current page URL (hash URL)
         */
        getPageUrl: function () {
            return this.pageUrl;
        },

        /**
         * Get the current page title
         *
         * @method     getPageTitle
         * @return     {String}  The current page title
         */
        getPageTitle: function () {
            return this.pageTitle;
        },

        /**
         * Get the current page parameters
         *
         * @method     getPageParameters
         * @return     {Object}  The current page parameters
         */
        getPageParameters: function () {
            return this.pageParameters;
        },

        /*=====  End of Getters  ======*/

        /*=========================================
        =            Utilities methods            =
        =========================================*/

        /**
         * Display a page by hide / show DOM elements and set pages attributes
         *
         * @method     loadPage
         * @param      {String}  pageUrl  The page URL to load
         */
        loadPage: function (pageUrl) {
            var currentPage = $(this.settings.selectors.currentPage),
                newPage     = $(this.settings.selectors.page + '[data-url="' + pageUrl + '"]'),
                pageTitle   = newPage.attr('data-title');
            // Check if the page exists
            if (!newPage || newPage.length < 1) {
                // @todo display error with message ?
                console.log('Page not found');
            } else {
                // Set page title
                $('title').html(pageTitle);
                // Hide the current page if exists
                if (currentPage.length > 0) {
                    currentPage.hide();
                    currentPage.removeClass(this.settings.selectors.currentPage.substr(1));
                }
                // Display the page
                newPage.show();
                newPage.addClass(this.settings.selectors.currentPage.substr(1));
                // Set the URL hash
                if (location.hash !== this.settings.urlPrefix + pageUrl) {
                    location.hash = this.settings.urlPrefix + pageUrl;
                }
                // Set self attributes
                this.page      = newPage;
                this.pageUrl   = pageUrl;
                this.pageTitle = pageTitle;
                // Run the callback if it is set
                if (this.callbacks[pageUrl]) {
                    this.callbacks[pageUrl].callback.call(this.callbacks[pageUrl].context);
                }
            }
        },

        /**
         * Load current page parameters in parsing the given URL hash
         *
         * @method     loadPageParameters
         * @param      {String}  hash    The URL hash
         */
        loadPageParameters: function (hash) {
            var parametersRaw = _.split(hash, '?'),
                parameters    = {},
                split;

            if (_.size(parametersRaw[1]) > 0) {
                parametersRaw = _.split(parametersRaw[1], '&');

                _.forEach(parametersRaw, function (parameter) {
                    split                = _.split(parameter, '=');
                    parameters[split[0]] = split[1];
                });

                this.parameters = parameters;
            }
        },

        /**
         * Load the landing page
         *
         * @method     loadLandingPage
         */
        loadLandingPage: function () {
            if (!this.loadPageFromHash()) {
                this.loadPage(this.settings.landingPage);
            }
        },

        /**
         * Add a callback to process data recieved from the WebSocket server
         *
         * @method     addCallback
         * @param      {String}    pageUrl   The callback page
         * @param      {Function}  callback  The callback function
         * @param      {Object}    context   The callback context (null to use the navigation this context)
         */
        addCallback: function (pageUrl, callback, context) {
            this.callbacks[pageUrl] = {
                "callback": callback,
                "context" : context || this
            };
        }

        /*=====  End of Utilities methods  ======*/
    };

    return Navigation;
});
