/* Static site data: categories, services, coupons, notifications, hero slides, etc. */

const SITE = {
    name: "TakshAllInOne",
    freeDeliveryMin: 299,
    deliveryFee: 29,
    supportPhone: "+91 99999 12345",
    supportEmail: "care@takshallinone.com"
};

const CATEGORIES = [
    { id: "grains",      name: "Atta, Rice & Dal",   emoji: "🌾", tint: "bg-amber-50",   ring: "hover:border-amber-400" },
    { id: "oils",        name: "Oils, Ghee & Masala", emoji: "🌻", tint: "bg-yellow-50",  ring: "hover:border-yellow-400" },
    { id: "dairy",       name: "Dairy & Bakery",     emoji: "🥛", tint: "bg-sky-50",     ring: "hover:border-sky-400" },
    { id: "snacks",      name: "Snacks & Sweets",    emoji: "🍿", tint: "bg-red-50",     ring: "hover:border-red-400" },
    { id: "beverages",   name: "Beverages",          emoji: "☕", tint: "bg-violet-50",  ring: "hover:border-violet-400" },
    { id: "fresh",       name: "Fruits & Vegetables", emoji: "🥦", tint: "bg-emerald-50", ring: "hover:border-emerald-400" },
    { id: "care",        name: "Personal Care",      emoji: "🧴", tint: "bg-pink-50",    ring: "hover:border-pink-400" },
    { id: "electronics", name: "Electronics",        emoji: "📱", tint: "bg-blue-50",    ring: "hover:border-blue-400" },
    { id: "decor",       name: "Festive Decor",      emoji: "🪔", tint: "bg-orange-50",  ring: "hover:border-orange-400" }
];

const HERO_SLIDES = [
    {
        badge: "✨ Diwali Special Mega Bonanza",
        title: "Everything You Need for Festive Joy, Delivered Fast!",
        text: "Groceries, authentic sweets, decor, electronics and home services - all in one tap. Up to 50% OFF.",
        cta: { label: "Shop Diwali Offers", page: "listing", params: { deals: 1 } },
        cta2: { label: "Festive Decor", page: "listing", params: { cat: "decor" } },
        bg: "from-orange-600 via-brand-500 to-brand-gold", emoji: "🪔"
    },
    {
        badge: "⚡ 15-Minute Delivery",
        title: "Fresh Milk, Veggies & Snacks at Your Door in 15 Minutes",
        text: "Daily essentials from your nearest Taksh dark store. Free delivery on orders above ₹299.",
        cta: { label: "Order in 15 Mins", page: "listing", params: { quick: 1 } },
        cta2: { label: "Fruits & Vegetables", page: "listing", params: { cat: "fresh" } },
        bg: "from-emerald-700 via-emerald-600 to-lime-500", emoji: "🥦"
    },
    {
        badge: "🛠️ Verified Home Services",
        title: "Electricians, Plumbers & Salon at Home - Book in 60 Seconds",
        text: "Background-verified professionals arriving within 60 minutes with upfront pricing.",
        cta: { label: "Book a Service", page: "services" },
        cta2: { label: "Courier Booking", page: "services", hash: "#courier" },
        bg: "from-purple-700 via-purple-600 to-fuchsia-500", emoji: "🔧"
    }
];

const SERVICES = [
    { id: "electrician", name: "Electrician",      emoji: "🔌", desc: "Wiring, switchboards, fan repair & lighting",   price: 199, rating: 4.8, tint: "bg-purple-50 text-purple-600" },
    { id: "plumber",     name: "Plumber",          emoji: "🔧", desc: "Leaks, tap fitting & bathroom repair",         price: 179, rating: 4.7, tint: "bg-emerald-50 text-emerald-600" },
    { id: "ac",          name: "AC Service",       emoji: "❄️", desc: "Deep cleaning, gas refill & installation",     price: 499, rating: 4.8, tint: "bg-sky-50 text-sky-600" },
    { id: "salon",       name: "Salon at Home",    emoji: "💇", desc: "Haircut, facial, waxing & grooming",           price: 299, rating: 4.9, tint: "bg-pink-50 text-pink-600" },
    { id: "cleaning",    name: "Home Cleaning",    emoji: "🧹", desc: "Deep cleaning for kitchen, bathroom & sofa",   price: 599, rating: 4.7, tint: "bg-amber-50 text-amber-600" },
    { id: "carpenter",   name: "Carpenter",        emoji: "🪚", desc: "Furniture repair, assembly & fittings",        price: 249, rating: 4.6, tint: "bg-orange-50 text-orange-600" },
    { id: "pest",        name: "Pest Control",     emoji: "🐜", desc: "Cockroach, termite & mosquito treatment",      price: 699, rating: 4.7, tint: "bg-red-50 text-red-600" },
    { id: "painter",     name: "Painter",          emoji: "🎨", desc: "Wall painting & Diwali touch-ups",             price: 899, rating: 4.6, tint: "bg-blue-50 text-blue-600" }
];

const COUPONS = [
    { code: "TAKSHDIWALI", type: "flat",    value: 50,  min: 199, desc: "Flat ₹50 off on orders above ₹199" },
    { code: "FIRSTORDER",  type: "flat",    value: 75,  min: 299, desc: "Flat ₹75 off on orders above ₹299" },
    { code: "FESTIVE10",   type: "percent", value: 10,  min: 499, max: 150, desc: "10% off up to ₹150 on orders above ₹499" }
];

const BASE_NOTIFICATIONS = [
    { id: "n1", kind: "offer", icon: "🪔", title: "Diwali Flash Sale Live!", text: "Get up to 50% discount on Atta, Oils and Sweets hampers today.", time: "2 hours ago" },
    { id: "n2", kind: "offer", icon: "🎟️", title: "Use TAKSHDIWALI for ₹50 off", text: "Apply the code at checkout on orders above ₹199.", time: "Yesterday" },
    { id: "n3", kind: "offer", icon: "⚡", title: "15-minute delivery is now live in Ahmedabad", text: "Fresh milk, vegetables and snacks delivered at lightning speed.", time: "2 days ago" },
    { id: "n4", kind: "offer", icon: "🛠️", title: "Home services - first booking at ₹99 off", text: "Electrician, plumber, AC service and more. Book now.", time: "3 days ago" }
];

const TESTIMONIALS = [
    { name: "Hetal Patel",  city: "Ahmedabad", text: "Ordered Diwali groceries and decor in one go - everything arrived the same evening. Prices are better than the local market!", rating: 5 },
    { name: "Rohan Mehta",  city: "Gandhinagar", text: "The 15-minute delivery is real. Milk and bread reached before my chai got cold. Super convenient.", rating: 5 },
    { name: "Sneha Shah",   city: "Vadodara", text: "Booked an electrician before the festival - on time, polite, and upfront pricing. Will use again.", rating: 4 }
];

const FAQS = [
    { q: "How fast is Quick Delivery?", a: "Items marked with the ⚡ badge are delivered in about 15 minutes from the nearest Taksh dark store (select pincodes)." },
    { q: "What are the delivery charges?", a: "Delivery is free on orders above ₹299. Below that, a flat ₹29 delivery fee applies." },
    { q: "Can I return a product?", a: "Yes. Most products have a 7-day easy return policy. Fresh items can be refunded instantly if not fresh on delivery." },
    { q: "Which payment methods are accepted?", a: "Cash on Delivery, UPI (Google Pay, PhonePe, Paytm) and credit/debit cards." },
    { q: "How are service professionals verified?", a: "All Taksh professionals pass ID, address and skill verification and are rated by customers after every job." }
];

const BRANDS_STRIP = ["Aashirvaad", "Amul", "Tata", "Fortune", "Haldiram's", "Cadbury", "Dove", "Philips", "boAt", "Nescafé"];
