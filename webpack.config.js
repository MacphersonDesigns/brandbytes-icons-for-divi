const path = require('path');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

/**
 * Divi owns React, WordPress packages, and its builder packages at runtime.
 * Marking them external keeps this plugin's bundle small and prevents a second
 * React/Divi runtime from being loaded into the Visual Builder iframe.
 */
module.exports = {
  entry: {
    bundle: './src/index.ts',
  },
  externals: {
    react: ['vendor', 'React'],
    'react-dom': ['vendor', 'ReactDOM'],
    '@wordpress/hooks': ['vendor', 'wp', 'hooks'],
    '@wordpress/i18n': ['vendor', 'wp', 'i18n'],
    '@divi/module': ['divi', 'module'],
    '@divi/field-library': ['divi', 'fieldLibrary'],
    '@divi/module-library': ['divi', 'moduleLibrary'],
    '@divi/module-utils': ['divi', 'moduleUtils'],
    '@divi/style-library': ['divi', 'styleLibrary'],
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        exclude: /node_modules/,
        use: {
          loader: 'ts-loader',
          options: {
            // Elegant Themes' current example uses transpileOnly because the
            // published Divi type packages lag parts of the builder runtime.
            // Jest metadata tests and the real builder fixture cover this
            // foundation until those upstream declarations converge.
            transpileOnly: true,
          },
        },
      },
      {
        test: /\.s?css$/i,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              url: false,
            },
          },
          'sass-loader',
        ],
      },
    ],
  },
  optimization: {
    splitChunks: {
      cacheGroups: {
        vb: {
          type: 'css/mini-extract',
          test: /[\\/]style(\.module)?\.(sc|sa|c)ss$/,
          chunks: 'all',
          enforce: true,
          name: 'vb-bundle',
        },
        default: false,
      },
    },
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].css',
    }),
    new CopyWebpackPlugin({
      patterns: [
        {
          from: '**/module.json',
          context: 'src/components',
          to: path.resolve(__dirname, 'modules-json'),
        },
        {
          from: '**/module-default-render-attributes.json',
          context: 'src/components',
          to: path.resolve(__dirname, 'modules-json'),
        },
        {
          from: '**/module-default-printed-style-attributes.json',
          context: 'src/components',
          to: path.resolve(__dirname, 'modules-json'),
        },
      ],
    }),
  ],
  resolve: {
    extensions: ['.js', '.jsx', '.ts', '.tsx', '.json'],
  },
  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'assets/divi5'),
  },
  stats: {
    errorDetails: true,
  },
};
