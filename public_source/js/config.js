require.config({
    baseUrl: '/js',
    paths: {
        requireLib: 'require'
    },
    shim: {
        'bootstrap': {
            deps: ['jquery']
        }
    }
});