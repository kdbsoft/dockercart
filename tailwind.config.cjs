/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './upload/**/*.twig',
    './upload/**/*.js',
    './upload/**/*.php',
    './html/**/*.html'
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Manrope', 'sans-serif']
      }
    }
  },
  plugins: []
}
