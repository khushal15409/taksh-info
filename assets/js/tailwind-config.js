/* Tailwind CDN theme config - must load after the Tailwind script */
tailwind.config = {
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
                    cardBg: '#FFFFFF',
                }
            },
            fontFamily: {
                sans: ['Inter', 'sans-serif'],
                festive: ['Outfit', 'sans-serif'],
            },
            boxShadow: {
                'festive': '0 10px 25px -5px rgba(230, 81, 0, 0.12), 0 8px 10px -6px rgba(230, 81, 0, 0.08)',
                'card-hover': '0 20px 30px -10px rgba(255, 122, 0, 0.18)',
            }
        }
    }
}
