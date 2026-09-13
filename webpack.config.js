const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

module.exports = {
  entry: {
    'admin/admin': './src/admin/js/admin.js',
    'admin/admin-style': './src/admin/css/admin.scss',
    'admin/admin-webinar-preview': './src/admin/css/webinar-preview.css',
    'public/webinar': './src/public/js/webinar.js',
    'public/webinar-style': './src/public/css/webinar.scss',
    'blocks/webinar': './src/blocks/webinar/index.js',
    'public/webinar-tabs': './src/public/js/webinar-tabs.js',
    'public/webinar-seek': './src/public/js/webinar-seek.js',
    'public/webinar-details': './src/public/js/webinar-details.js',
    'public/webinar-slider': './src/public/js/webinar-slider.js',
    'public/webinar-controls': './src/public/js/webinar-controls.js',
  },
  output: {
    path: path.resolve(__dirname, 'build'),
    filename: '[name].js',
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
        },
      },
      {
        test: /\.scss$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
          {
            loader: 'sass-loader',
            options: {
              api: 'modern',
            },
          },
        ],
      },
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
        ],
      },
    ],
  },
  plugins: [
    new RemoveEmptyScriptsPlugin(),  // ← добавляем первым
    new MiniCssExtractPlugin({
      filename: '[name].css',
    }),
  ],
  externals: {
    'react': 'React',
    'react-dom': 'ReactDOM',
    '@wordpress/blocks': ['wp', 'blocks'],
    '@wordpress/element': ['wp', 'element'],
    '@wordpress/components': ['wp', 'components'],
    '@wordpress/data': ['wp', 'data'],
    '@wordpress/i18n': ['wp', 'i18n'],
  },
  performance: {
    hints: false,
  },
};