var webpack = require('webpack');
var path = require("path");
var ExtractTextPlugin = require("extract-text-webpack-plugin");
var ManifestPlugin = require('webpack-manifest-plugin');
var CompressionPlugin = require("compression-webpack-plugin");
var HtmlWebpackPlugin = require('html-webpack-plugin');
var FaviconsWebpackPlugin = require('favicons-webpack-plugin');

module.exports = {
    context: __dirname,
    entry: {
        application: 'application.js'
    },
    resolve: {
        modules: [
            'assets',
            'node_modules'
        ],
        alias: {
            requireLib: 'require',
            chart: require.resolve('chart.js'),
            typeahead: require.resolve('typeahead.js'),
            markdown: require.resolve('markdown/lib/markdown.js')
        }
    },
    module: {
        rules: [
            { test: /webgl-heatmap-leaflet/, use: {
                loader: 'imports-loader',
                options: {'L': 'leaflet'}
            }},
            { test: /bootstrap/, use: {
                loader: 'imports-loader',
                options: {'jQuery': 'jquery'}
            }},
            { test: /Jcrop/, use: {
                loader: 'imports-loader',
                options: {'jQuery': 'jquery'}
            }},
            { test: /tagsinput/, use: {
                loader: 'imports-loader',
                options: {'window.jQuery': 'jquery'}
            }},
            { 
                test: /.html$/,
                use: {
                    loader: "html-loader",
                    options: {
                        minimize: true
                    }
                }
            },
            {
                test: /\.css$/,
                use: ExtractTextPlugin.extract({
                    fallback: "style-loader", 
                    use: "css-loader"
                })
            },
            {
                test: /\.less$/,
                use: ExtractTextPlugin.extract({
                    fallback: "style-loader", 
                    use: "css-loader!less-loader"
                })
            },
            //{test: /\.(jpe?g|png|gif|svg)$/i, loader: "file-loader?name=img/[name].[ext]"},
            {
                test: /\.(jpe?g|png|gif|svg)$/i,
                use: [
                    {
                        loader: 'url-loader',
                        options: {
                            prefix: 'img/',
                            limit: 10000,
                            hash: 'sha512',
                            digest: 'hex',
                            name: 'img/[hash].[ext]'
                        }
                    },
                    {
                        loader: 'image-webpack-loader',
                        options: {
                            //bypassOnDebug: true,
                            optipng: {
                                optimizationLevel: 5,
                            },
                            gifsicle: {
                                interlaced: false
                            }
                        }
                    }
                ]
            },
            { 
                test: /\.woff(2)?(\?v=[0-9]\.[0-9]\.[0-9])?$/,
                use: {
                    loader: "url-loader",
                    options: {
                        name: 'fonts/[hash].[ext]', 
                        limit: 10000,
                        mimetype: 'application/font-woff'
                    }
                }
            },
            { 
                test: /fontawesome-webfont\.(ttf|eot|svg)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
                use: {
                    loader: "file-loader",
                    options: {
                        name: 'fonts/[hash].[ext]'
                    }
                }
            },
            { 
                test: /glyphicons-halflings-regular\.(ttf|eot|svg)$/,
                use: {
                    loader: "file-loader",
                    options: {
                        name: 'fonts/[hash].[ext]'
                    }
                }
            }
        ]
    },
    plugins: [
        new webpack.LoaderOptionsPlugin({
            minimize: true,
            debug: false
        }),
        new webpack.optimize.UglifyJsPlugin({
            compress: {
                warnings: false,
                screw_ie8: true,
                conditionals: true,
                unused: true,
                comparisons: true,
                sequences: true,
                dead_code: true,
                evaluate: true,
                if_return: true,
                join_vars: true,
            },
            output: {
                comments: false,
            },
        }),
        new ExtractTextPlugin({
            filename: "css/[name].[hash].css"
        }),
        new ManifestPlugin(),
        new CompressionPlugin({
            asset: "[path].gz[query]",
            algorithm: "gzip",
            test: /\.js$|\.css$|\.svg$|\.eot$|\.woff2?$|\.ttf$/,
            threshold: 10240,
            minRatio: 0.8
        }),
        //new webpack.optimize.CommonsChunkPlugin("vendor", "js/vendor.bundle.js"), //.[chunkhash]
        new HtmlWebpackPlugin(),
        new FaviconsWebpackPlugin({ // Your source logo
            logo: __dirname + '/module/Application/assets/logo-bg.svg',
            // The prefix for all image files (might be a folder or a name)
            prefix: 'icons-[hash]/',
            // Emit all stats of the generated icons
            emitStats: true,
            // The name of the json containing all favicon information
            statsFilename: 'iconstats.json',
            // Generate a cache file with control hashes and
            // don't rebuild the favicons until those hashes change
            persistentCache: true,
            // Inject the html into the html-webpack-plugin
            inject: true,
            // favicon background color (see https://github.com/haydenbleasel/favicons#usage)
            background: '#ede9de',
            // favicon app title (see https://github.com/haydenbleasel/favicons#usage)
            title: 'WheelsAge.org',

            // which icons should be generated (see https://github.com/haydenbleasel/favicons#usage)
            icons: {
                android: true,
                appleIcon: true,
                appleStartup: true,
                coast: true,
                favicons: true,
                firefox: true,
                opengraph: true,
                twitter: true,
                yandex: true,
                windows: true
            }
        })
    ],
    output: {
        path: path.join(__dirname, "public_html/dist"),
        filename: "js/[name].[chunkhash].js",
        publicPath: "/dist/"
    }
}