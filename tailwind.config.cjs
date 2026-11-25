/** Tailwind v3 config - compiled once into public/assets/css/tailwind.css (see package.json "build:css"). Same theme as the old CDN config. */
module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './public/assets/js/**/*.js',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#FFF8ED',
                    100: '#FFEED4',
                    200: '#FFD7AA',
                    500: '#FF7A00', /* Primary Saffron */
                    600: '#E65100', /* Deep Warm Festive */
                    700: '#C23B00',
                    gold: '#FFA000',
                    green: '#2E7D32',
                    cream: '#FFFBEF',
                },
            },
            fontFamily: {
                sans: ['Inter', 'sans-serif'],
                festive: ['Outfit', 'sans-serif'],
            },
            boxShadow: {
                festive: '0 10px 25px -5px rgba(230, 81, 0, 0.12), 0 8px 10px -6px rgba(230, 81, 0, 0.08)',
                'card-hover': '0 20px 30px -10px rgba(255, 122, 0, 0.18)',
            },
        },
    },
};
