({
    // baseUrl: "lib",
    name: "app.js",
    mainConfigFile: 'app.js',
    out: "../dist/app.js",
    optimize: "uglify2",
    preserveLicenseComments: false,
    generateSourceMaps: true,
    optimizeAllPluginResources: true, // usefull ?
    findNestedDependencies: true,
    wrap: true,
    include: ["lib/vendor/require.js"]
})
