import { Link } from '@inertiajs/react';
import { FileText, Book, GraduationCap, Users, Github, Mail, Building, ExternalLink } from 'lucide-react';
import AppLogoIcon from './app-logo-icon';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="bg-gradient-to-b from-white to-gray-50 dark:from-gray-900 dark:to-gray-950 border-t border-gray-200 dark:border-gray-800">
            <div className="max-w-7xl mx-auto py-16 px-6 sm:px-8 lg:px-8">
                {/* Mobile view - Stack all columns */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-y-10 gap-x-8">
                    {/* Project Identity with Logo - Takes more space on large screens */}
                    <div className="lg:col-span-4 space-y-6">
                        <Link href={route('home')} className="flex items-center space-x-3 group" aria-label="Home">
                            <div className="h-12 w-12 rounded-xl overflow-hidden transform transition-transform duration-300 group-hover:scale-110 shadow-lg">
                                <AppLogoIcon className="w-full h-full object-cover" />
                            </div>
                            <span className="font-bold text-xl bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent group-hover:from-teal-500 group-hover:to-teal-300 transition-all duration-300">ProcuChain</span>
                        </Link>
                        <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed max-w-md">
                            A blockchain-based procurement management system developed as an academic capstone project.
                        </p>
                        <div className="pt-3 space-y-3">
                            <div className="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400 transition-colors duration-200 hover:text-gray-800 dark:hover:text-gray-300">
                                <Building className="w-4 h-4 flex-shrink-0" />
                                <span>Mindoro State University - Bongabong Campus</span>
                            </div>
                            <div className="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400 transition-colors duration-200 hover:text-gray-800 dark:hover:text-gray-300">
                                <GraduationCap className="w-4 h-4 flex-shrink-0" />
                                <span>Capstone Project 2025</span>
                            </div>
                        </div>
                    </div>

                    {/* Columns container for the three right sections */}
                    <div className="lg:col-span-8 grid grid-cols-1 sm:grid-cols-3 gap-y-10 gap-x-8">
                        {/* Project Resources */}
                        <div className="space-y-5">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white relative after:absolute after:content-[''] after:bottom-0 after:left-0 after:w-12 after:h-0.5 after:bg-teal-500 after:-bottom-2">Project Resources</h3>
                            <ul className="space-y-4">
                                <li>
                                    <Link href="/methodology" className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 group">
                                        <span className="bg-gray-100 dark:bg-gray-800 rounded-full p-1.5 mr-3 group-hover:bg-teal-100 dark:group-hover:bg-teal-900/30 transition-colors duration-200">
                                            <Book className="w-4 h-4" />
                                        </span>
                                        <span className="group-hover:translate-x-1 transition-transform duration-200">Research Methodology</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/documentation" className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 group">
                                        <span className="bg-gray-100 dark:bg-gray-800 rounded-full p-1.5 mr-3 group-hover:bg-teal-100 dark:group-hover:bg-teal-900/30 transition-colors duration-200">
                                            <FileText className="w-4 h-4" />
                                        </span>
                                        <span className="group-hover:translate-x-1 transition-transform duration-200">Technical Documentation</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/team" className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors duration-200 group">
                                        <span className="bg-gray-100 dark:bg-gray-800 rounded-full p-1.5 mr-3 group-hover:bg-teal-100 dark:group-hover:bg-teal-900/30 transition-colors duration-200">
                                            <Users className="w-4 h-4" />
                                        </span>
                                        <span className="group-hover:translate-x-1 transition-transform duration-200">Research Team</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        {/* Technical Specifications */}
                        <div className="space-y-5">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white relative after:absolute after:content-[''] after:bottom-0 after:left-0 after:w-12 after:h-0.5 after:bg-teal-500 after:-bottom-2">Tech Stack</h3>
                            <div className="grid grid-cols-1 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
                                <div className="flex items-center space-x-3 group transition-transform duration-200 hover:translate-x-1">
                                    <div className="w-2 h-2 rounded-full bg-teal-500"></div>
                                    <span>Laravel</span>
                                </div>
                                <div className="flex items-center space-x-3 group transition-transform duration-200 hover:translate-x-1">
                                    <div className="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <span>React</span>
                                </div>
                                <div className="flex items-center space-x-3 group transition-transform duration-200 hover:translate-x-1">
                                    <div className="w-2 h-2 rounded-full bg-green-500"></div>
                                    <span>TypeScript</span>
                                </div>
                                <div className="flex items-center space-x-3 group transition-transform duration-200 hover:translate-x-1">
                                    <div className="w-2 h-2 rounded-full bg-purple-500"></div>
                                    <span>MultiChain</span>
                                </div>
                                <div className="flex items-center space-x-3 group transition-transform duration-200 hover:translate-x-1">
                                    <div className="w-2 h-2 rounded-full bg-cyan-500"></div>
                                    <span>Tailwind</span>
                                </div>
                                <div className="flex items-center space-x-3 group transition-transform duration-200 hover:translate-x-1">
                                    <div className="w-2 h-2 rounded-full bg-orange-500"></div>
                                    <span>Inertia.js</span>
                                </div>
                            </div>
                        </div>

                        {/* Contact Information */}
                        <div className="space-y-5">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white relative after:absolute after:content-[''] after:bottom-0 after:left-0 after:w-12 after:h-0.5 after:bg-teal-500 after:-bottom-2">Contact</h3>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">Faculty Advisor</p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Mr. Uriel M. Melendres</p>
                                </div>
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">Project Email</p>
                                    <a
                                        href="mailto:procuchain@university.edu"
                                        className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 group transition-colors duration-200"
                                    >
                                        <span className="bg-gray-100 dark:bg-gray-800 rounded-full p-1.5 mr-3 group-hover:bg-teal-100 dark:group-hover:bg-teal-900/30 transition-colors duration-200">
                                            <Mail className="w-4 h-4" />
                                        </span>
                                        <span className="group-hover:translate-x-1 transition-transform duration-200">procuchain@university.edu</span>
                                    </a>
                                </div>
                                <div className="flex items-center space-x-4 pt-3">
                                    <a
                                        href="https://github.com/procuchain"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 p-2 rounded-full hover:bg-teal-100 hover:text-teal-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400 transition-all duration-300 transform hover:scale-110"
                                        aria-label="GitHub Repository"
                                    >
                                        <Github className="w-5 h-5" />
                                    </a>
                                    <a
                                        href="/research-paper.pdf"
                                        target="_blank"
                                        className="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 p-2 rounded-full hover:bg-teal-100 hover:text-teal-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400 transition-all duration-300 transform hover:scale-110"
                                        aria-label="Research Paper"
                                    >
                                        <ExternalLink className="w-5 h-5" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Attribution and Copyright */}
                <div className="mt-16 pt-8 border-t border-gray-200 dark:border-gray-800">
                    <div className="text-center space-y-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            © {currentYear} ProcuChain - Academic Capstone Project
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-500">
                            Developed under the guidance of the Department of Information Technology, Mindoro State University - Bongabong Campus
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    );
}
