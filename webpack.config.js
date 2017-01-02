var webpack = require('webpack');
var path = require("path");
var ExtractTextPlugin = require("extract-text-webpack-plugin");
var ManifestPlugin = require('webpack-manifest-plugin');
var CompressionPlugin = require("compression-webpack-plugin");
var HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = {
    context: __dirname,
    entry: {
        application: 'application.js',
        /*vendor: ["jquery", "typeahead.js", 
            "font-awesome-webpack", "chart.js", "raphael", "filesize", 
            "load-google-maps-api", 'jcrop-0.9.12/css/jquery.Jcrop.css', 
            'jcrop-0.9.12/js/jquery.Jcrop', "markdown", "diff", 
            'bootstrap-tagsinput'],*/
    },
    resolve: {
        modulesDirectories: ['assets', 'node_modules'],
        alias: {
            requireLib: 'require',
            chart: require.resolve('chart.js'),
            typeahead: require.resolve('typeahead.js'),
            markdown: require.resolve('markdown/lib/markdown.js')
        },
        
    },
    module: {
        loaders: [
            { test: /bootstrap/, loader: 'imports?jQuery=jquery' },
            { test: /Jcrop/, loader: 'imports?jQuery=jquery' },
            { test: /bootstrap-tagsinput/, loader: 'imports?window.jQuery=jquery' },
            { test: "\.html$/", loader: "html" },
            {
                test: /\.css$/,
                loader: ExtractTextPlugin.extract("style-loader", "css-loader")
            },
            {
                test: /\.less$/,
                loader: ExtractTextPlugin.extract("style-loader", "css-loader!less-loader")
            },
            //{test: /\.(jpe?g|png|gif|svg)$/i, loader: "file-loader?name=img/[name].[ext]"},
            {
                test: /\.(jpe?g|png|gif|svg)$/i,
                loaders: [
                    'url-loader?prefix=img/&limit=10000&hash=sha512&digest=hex&name=img/[hash].[ext]',
                    'image-webpack?bypassOnDebug&optimizationLevel=7&interlaced=false'
                ]
            },
            { test: /\.woff(2)?(\?v=[0-9]\.[0-9]\.[0-9])?$/, loader: "url-loader?name=fonts/[hash].[ext]&limit=10000&mimetype=application/font-woff" },
            { test: /fontawesome-webfont\.(ttf|eot|svg)(\?v=[0-9]\.[0-9]\.[0-9])?$/, loader: "file-loader?name=fonts/[hash].[ext]" },
            
            { test: /glyphicons-halflings-regular\.(ttf|eot|svg)$/, loader: "file-loader?name=fonts/[hash].[ext]" },
        ],
    },
    plugins: [
        new webpack.optimize.UglifyJsPlugin({minimize: true}),
        new ExtractTextPlugin("css/[name].[hash].css"),
        new ManifestPlugin(),
        new CompressionPlugin({
            asset: "[path].gz[query]",
            algorithm: "gzip",
            test: /\.js$|\.css$|\.svg$|\.eot$|\.woff2?$|\.ttf$/,
            threshold: 10240,
            minRatio: 0.8
        }),
        //new webpack.optimize.CommonsChunkPlugin("vendor", "js/vendor.bundle.js"), //.[chunkhash]
        new HtmlWebpackPlugin()
    ],
    output: {
        path: path.join(__dirname, "public_html/dist"),
        filename: "js/[name].[chunkhash].js",
        publicPath: "/dist/"
    }
}