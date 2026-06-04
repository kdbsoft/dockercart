/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './upload/**/*.twig',
    './upload/**/*.js',
    './upload/**/*.php',
    './html/**/*.html'
  ],
  safelist: [
    // dockercart_shop_features palette — dynamically assigned in PHP controller
    { pattern: /bg-(blue|teal|rose|indigo|purple|green|orange|red)-(100|600)/ },
    { pattern: /text-(blue|teal|rose|indigo|purple|green|orange|red)-(500|600)/ },
    'group-hover:bg-blue-600',
    'group-hover:bg-teal-500',
    'group-hover:bg-rose-500',
    'group-hover:bg-indigo-600',
    'group-hover:bg-purple-600',
    'group-hover:bg-green-600',
    'group-hover:bg-orange-600',
    'group-hover:bg-red-600',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Manrope', 'sans-serif']
      }
    }
  },
  plugins: [
    require('@tailwindcss/typography')
  ]
}
