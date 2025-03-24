export default function Footer() {
    return (
        <footer className="py-6 px-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
            <div className="max-w-7xl mx-auto text-center">
                <p className="text-sm text-gray-600 dark:text-gray-400">
                    © {new Date().getFullYear()} ProcuChain. All rights reserved.
                </p>
            </div>
        </footer>
    );
}
