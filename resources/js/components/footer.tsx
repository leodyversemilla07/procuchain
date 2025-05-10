import { Link } from '@inertiajs/react';
import { Mail, Github, Home, Info, Layers, Users, FileArchive, PlayCircle, Download, MapPin, Code, Users as TeamIcon, GraduationCap, Twitter, Linkedin } from 'lucide-react';
import AppLogoIcon from './app-logo-icon';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    const navigationLinks = [
        { name: 'Home', href: route('home'), icon: <Home className="w-4 h-4" /> },
        { name: 'About', href: route('about'), icon: <Info className="w-4 h-4" /> },
        { name: 'Features', href: route('features'), icon: <Layers className="w-4 h-4" /> },
        { name: 'Development', href: route('development'), icon: <Code className="w-4 h-4" /> },
        { name: 'Team', href: route('team'), icon: <TeamIcon className="w-4 h-4" /> },
        { name: 'Documentation', href: route('documentation'), icon: <FileArchive className="w-4 h-4" /> },
        { name: 'Contact', href: route('contact'), icon: <Mail className="w-4 h-4" /> },
    ];

    const callToActionLinks = [
        { name: 'View GitHub Repo', href: 'https://github.com/leodyversemilla07/procuchain', icon: <Github className="w-4 h-4" />, target: '_' + 'blank' },
        { name: 'Try Live Demo', href: '#', icon: <PlayCircle className="w-4 h-4" />, target: '_' + 'self' },
        { name: 'Download PDF Report', href: '/docs/capstone_report.pdf', icon: <Download className="w-4 h-4" />, target: '_' + 'blank' },
    ];

    const socialLinks = [
        { name: 'GitHub', href: 'https://github.com/leodyversemilla07/procuchain', icon: <Github className="w-5 h-5" /> },
        { name: 'Twitter', href: '#', icon: <Twitter className="w-5 h-5" /> },
        { name: 'LinkedIn', href: '#', icon: <Linkedin className="w-5 h-5" /> },
        { name: 'Minsu', href: 'https://minsu.edu.ph', icon: <GraduationCap className="w-5 h-5" /> },
    ];

    return (
        <footer className="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12 mb-12">
                    <div className="col-span-1">
                        <div className="flex items-center space-x-3 mb-4">
                            <div className="h-10 w-10 rounded-xl overflow-hidden shadow-sm flex-shrink-0 transform transition-transform duration-300 hover:scale-105">
                                <AppLogoIcon className="w-full h-full object-cover" />
                            </div>
                            <span className="font-bold text-xl bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                ProcuChain
                            </span>
                        </div>
                        <p className="text-gray-600 dark:text-gray-300 text-sm mb-6">
                            A blockchain-based system for transparent and efficient government procurement. Capstone project for BSIT 2025 - 2026.
                        </p>
                        <div className="flex space-x-4">
                            {socialLinks.map((link, index) => (
                                <a
                                    key={index}
                                    href={link.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-gray-500 hover:text-teal-600 dark:text-gray-400 dark:hover:text-teal-400 transition-colors"
                                    aria-label={link.name}
                                >
                                    {link.icon}
                                </a>
                            ))}
                        </div>
                    </div>

                    <div className="col-span-1">
                        <h3 className="font-semibold text-gray-900 dark:text-white mb-4">Navigation</h3>
                        <ul className="space-y-2">
                            {navigationLinks.map((link, index) => (
                                <li key={index}>
                                    <Link
                                        href={link.href}
                                        className="text-gray-600 hover:text-teal-600 dark:text-gray-300 dark:hover:text-teal-400 flex items-center transition-colors text-sm group"
                                    >
                                        <div className="w-5 h-5 mr-2 flex items-center justify-center flex-shrink-0 text-gray-500 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">
                                            {link.icon}
                                        </div>
                                        {link.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="col-span-1">
                        <h3 className="font-semibold text-gray-900 dark:text-white mb-4">Actions</h3>
                        <ul className="space-y-2">
                            {callToActionLinks.map((link, index) => (
                                <li key={index}>
                                    <a
                                        href={link.href}
                                        target={link.target}
                                        rel={link.target === '_' + 'blank' ? "noopener noreferrer" : undefined}
                                        className="text-gray-600 hover:text-teal-600 dark:text-gray-300 dark:hover:text-teal-400 flex items-center transition-colors text-sm group"
                                    >
                                        <div className="w-5 h-5 mr-2 flex items-center justify-center flex-shrink-0 text-gray-500 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">
                                            {link.icon}
                                        </div>
                                        {link.name}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="col-span-1">
                        <h3 className="font-semibold text-gray-900 dark:text-white mb-4">Contact & Info</h3>
                        <div className="text-sm text-gray-600 dark:text-gray-300 space-y-3">
                            <p className="flex items-start group">
                                <Mail className="w-4 h-4 mr-2 mt-1 flex-shrink-0 text-gray-500 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" />
                                <a href="mailto:semilla.leodyver@minsu.edu.ph" className="hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                                    semilla.leodyver@minsu.edu.ph
                                </a>
                            </p>
                            <p className="flex items-start group">
                                <Users className="w-4 h-4 mr-2 mt-1 flex-shrink-0 text-gray-500 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" />
                                <span>Mindoro State University<br />Bongabong Campus<br />College of Computer Studies</span>
                            </p>
                            <p className="flex items-start group">
                                <MapPin className="w-4 h-4 mr-2 mt-1 flex-shrink-0 text-gray-500 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors" />
                                <span>Bongabong, Oriental Mindoro</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div className="pt-8 mt-8 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
                    <div className="text-center sm:text-left text-sm text-gray-600 dark:text-gray-400">
                        <span>© {currentYear} ProcuChain. All rights reserved.</span>
                        <span className="mx-2 hidden sm:inline">|</span>
                        <br className="sm:hidden" />
                        <span>Built by Leodyver S. Semilla</span>
                    </div>
                    <div className="flex space-x-6 text-sm">
                        <Link href="/privacy" className="text-gray-600 hover:text-teal-600 dark:text-gray-400 dark:hover:text-teal-400 transition-colors">
                            Privacy Policy
                        </Link>
                        <Link href="/terms" className="text-gray-600 hover:text-teal-600 dark:text-gray-400 dark:hover:text-teal-400 transition-colors">
                            Terms of Service
                        </Link>
                    </div>
                </div>
            </div>
        </footer>
    );
}
