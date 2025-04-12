import { Link } from '@inertiajs/react';
import { FileText, Book, GraduationCap, Users, Github, Mail, Building, ExternalLink } from 'lucide-react';
import AppLogoIcon from './app-logo-icon';

export default function Footer() {
    const currentYear = new Date().getFullYear();
    
    return (
        <footer className="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
            <div className="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    {/* Project Identity with Logo */}
                    <div className="space-y-6">
                        <Link href={route('home')} className="flex items-center space-x-3 group" aria-label="Home">
                            <div className="h-11 w-11 rounded-xl overflow-hidden transform transition-all duration-300 group-hover:scale-105 group-hover:rotate-3 shadow-lg">
                                <AppLogoIcon className="w-full h-full object-cover" />
                            </div>
                            <span className="font-bold text-xl bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent group-hover:to-teal-500 transition-all duration-300">ProcuChain</span>
                        </Link>
                        <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            A blockchain-based procurement management system developed as an academic capstone project.
                        </p>
                        <div className="pt-2 space-y-2">
                            <div className="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                                <Building className="w-4 h-4" />
                                <span>Mindoro State University - Bongabong Campus</span>
                            </div>
                            <div className="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                                <GraduationCap className="w-4 h-4" />
                                <span>Capstone Project 2025</span>
                            </div>
                        </div>
                    </div>

                    {/* Project Resources */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Project Resources</h3>
                        <ul className="space-y-3">
                            <li>
                                <Link href="/methodology" className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400">
                                    <Book className="w-4 h-4 mr-2" />
                                    Research Methodology
                                </Link>
                            </li>
                            <li>
                                <Link href="/documentation" className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400">
                                    <FileText className="w-4 h-4 mr-2" />
                                    Technical Documentation
                                </Link>
                            </li>
                            <li>
                                <Link href="/team" className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400">
                                    <Users className="w-4 h-4 mr-2" />
                                    Research Team
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Technical Specifications */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Tech Stack</h3>
                        <div className="grid grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div className="space-y-2">
                                <div className="flex items-center space-x-2">
                                    <div className="w-2 h-2 rounded-full bg-teal-500"></div>
                                    <span>Laravel</span>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <div className="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <span>React</span>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <div className="w-2 h-2 rounded-full bg-green-500"></div>
                                    <span>TypeScript</span>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center space-x-2">
                                    <div className="w-2 h-2 rounded-full bg-purple-500"></div>
                                    <span>MultiChain</span>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <div className="w-2 h-2 rounded-full bg-cyan-500"></div>
                                    <span>Tailwind</span>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <div className="w-2 h-2 rounded-full bg-orange-500"></div>
                                    <span>Inertia.js</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Contact Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Contact</h3>
                        <div className="space-y-3">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-900 dark:text-white">Faculty Advisor</p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Mr. Uriel M. Melendres</p>
                            </div>
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-900 dark:text-white">Project Email</p>
                                <a 
                                    href="mailto:procuchain@university.edu"
                                    className="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400"
                                >
                                    <Mail className="w-4 h-4 mr-2" />
                                    procuchain@university.edu
                                </a>
                            </div>
                            <div className="flex items-center space-x-4 pt-2">
                                <a
                                    href="https://github.com/procuchain"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400"
                                    aria-label="GitHub Repository"
                                >
                                    <Github className="w-5 h-5" />
                                </a>
                                <a
                                    href="/research-paper.pdf"
                                    target="_blank"
                                    className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400"
                                    aria-label="Research Paper"
                                >
                                    <ExternalLink className="w-5 h-5" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Attribution and Copyright */}
                <div className="mt-12 pt-8 border-t border-gray-200 dark:border-gray-800">
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
