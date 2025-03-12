export default function Footer() {
    return (
        <footer className="px-6 md:px-12 py-12 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
            <div className="max-w-7xl mx-auto">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
                    <div>
                        <div className="flex items-center space-x-2 mb-6">
                            <div className="h-9 w-9 rounded-md bg-gradient-to-r from-teal-600 to-teal-500 flex items-center justify-center">
                                <span className="text-white font-bold text-lg">P</span>
                            </div>
                            <span className="font-semibold text-lg text-gray-900 dark:text-white">ProcuChain</span>
                        </div>
                        <p className="text-gray-600 dark:text-gray-400 mb-4">Revolutionizing BAC document management with blockchain technology</p>
                    </div>

                    {[
                        {
                            title: "Platform",
                            links: ["Features", "Security", "Compliance", "Resources"]
                        },
                        {
                            title: "Support",
                            links: ["Documentation", "Training", "Help Center", "API"]
                        },
                        {
                            title: "Legal",
                            links: ["Privacy", "Terms", "Security", "Data Retention"]
                        }
                    ].map((column, i) => (
                        <div key={i}>
                            <h3 className="font-semibold text-gray-900 dark:text-white mb-4">{column.title}</h3>
                            <ul className="space-y-3">
                                {column.links.map((link, j) => (
                                    <li key={j}>
                                        <a href="#" className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                                            {link}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>

                <div className="pt-8 border-t border-gray-200 dark:border-gray-800 flex flex-col md:flex-row justify-between items-center">
                    <div className="text-sm text-gray-600 dark:text-gray-400 mb-4 md:mb-0">
                        © {new Date().getFullYear()} ProcuChain - Bids and Awards Committee Document Management System. All rights reserved.
                    </div>
                    <div className="flex space-x-6">
                        {["Facebook", "LinkedIn", "Twitter", "Email"].map((social, i) => (
                            <a key={i} href="#" className="text-gray-500 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                                {social}
                            </a>
                        ))}
                    </div>
                </div>
            </div>
        </footer>
    );
}
