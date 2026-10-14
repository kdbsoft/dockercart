/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './upload/**/*.twig',
    './upload/**/*.js',
    './upload/{admin/{controller,model,language,view,cli},catalog,system,bin,cron}/**/*.php',
    './upload/index.php',
    './upload/healthcheck.php',
    './html/**/*.html'
  ],
  safelist: [
    // Kept for class names passed as strings between Twig/JS (product card button themes)
    'group-hover:bg-blue-600',
    'hover:bg-blue-600',
    'hover:bg-emerald-600',
    'hover:bg-amber-600',
    'group-hover:bg-amber-600',
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
