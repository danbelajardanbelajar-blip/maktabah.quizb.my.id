/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.php",
    "./**/*.php",
    "./js/**/*.js"
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary:  { DEFAULT: '#166534', light: '#15803D', dark: '#14532D' },
        gold:     { DEFAULT: '#C9A227', light: '#F4E7B2', dark: '#A1821F' },
        cream:    { DEFAULT: '#F8FAF9', dark: '#F0FDF4' },
        ink:      { DEFAULT: '#1F2937' },
      },
      fontFamily: {
        latin:  ['Lato', 'sans-serif'],
        arabic: ['"Amiri"', '"Noto Naskh Arabic"', 'serif'],
      },
      boxShadow: {
        card: '0 2px 16px 0 rgba(22,101,52,.08)',
        'card-hover': '0 8px 32px 0 rgba(22,101,52,.16)',
      },
    }
  }
}
