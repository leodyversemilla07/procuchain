import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { GraduationCap, Users, BookOpen, Mail, ExternalLink, Github, Linkedin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function Team() {
    const teamMembers = [
        {
            name: "Leodyver S. Semilla",
            role: "Team Leader / Developer",
            bio: "Led the team and completed the full development of ProcuChain, implementing blockchain integration, system architecture, frontend, and backend development.",
            responsibilities: ["Team Leadership", "Full System Development", "Blockchain Integration", "System Architecture"],
            image: "/images/team/leodyver.jpg",
            github: "https://github.com/leodyver",
            linkedin: "https://linkedin.com/in/leodyver-semilla",
            email: "leodyver.semilla@minsu.edu.ph",
        },
        {
            name: "Bryle F. Maamo",
            role: "Lead Researcher / Documentation",
            bio: "Led the research efforts and authored the research paper, focusing on procurement processes and blockchain implementation theory.",
            responsibilities: ["Research Paper Writing", "Literature Review", "Methodology Development", "Documentation"],
            image: "/images/team/bryle.jpg",
            github: "https://github.com/brylemaamo",
            linkedin: "https://linkedin.com/in/bryle-maamo",
            email: "bryle.maamo@minsu.edu.ph",
        },
        {
            name: "Adrian P. Gupit",
            role: "Research Support / Assistant",
            bio: "Provided support to the research efforts, assisting in data gathering and documentation for the research paper.",
            responsibilities: ["Research Assistance", "Data Collection", "Documentation Support", "Reference Management"],
            image: "/images/team/adrian.jpg",
            github: "https://github.com/adriangupit",
            linkedin: "https://linkedin.com/in/adrian-gupit",
            email: "adrian.gupit@minsu.edu.ph",
        },
    ];

    const advisers = [
        {
            name: "Mr. Uriel M. Melendres",
            role: "Project Adviser",
            bio: "Guided the team throughout the development process, providing expertise in blockchain technology and research methodology.",
            image: "/images/team/adviser.png",
            email: "uriel.melendres@minsu.edu.ph",
            specialization: "Blockchain Technology & Information Systems"
        }
    ];

    return (
        <>
            <Head title="Research Team">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="Meet the team behind ProcuChain - the innovative blockchain-powered procurement system." />
            </Head>

            <div className="min-h-screen flex flex-col bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
                <Header />

                <main className="flex-grow pt-24 pb-16">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="mb-16 text-center">
                            <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                                <Users className="w-6 h-6" />
                            </div>
                            <h1 className="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                                <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                    Meet Our Team
                                </span>
                            </h1>
                            <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-3xl mx-auto">
                                The innovative minds behind ProcuChain - dedicated to revolutionizing government procurement 
                                processes through blockchain technology
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-teal-50 dark:bg-teal-900/30 border-teal-200 dark:border-teal-800">
                                    <GraduationCap className="w-3.5 h-3.5 mr-1" />
                                    Mindoro State University - Bongabong Campus
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800">
                                    <BookOpen className="w-3.5 h-3.5 mr-1" />
                                    Class of 2022 - 2026
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-purple-50 dark:bg-purple-900/30 border-purple-200 dark:border-purple-800">
                                    <Users className="w-3.5 h-3.5 mr-1" />
                                    Information Technology
                                </Badge>
                            </div>
                        </div>

                        {/* Adviser Section */}
                        <div className="mb-16">
                            <h2 className="text-2xl font-bold mb-8 text-center">Project Adviser</h2>
                            
                            <div className="max-w-3xl mx-auto">
                                {advisers.map((adviser, index) => (
                                    <div
                                        key={index}
                                        className="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 flex flex-col md:flex-row gap-8 items-center hover:shadow-xl transition-shadow duration-300 hover:-translate-y-1"
                                    >
                                        <div className="md:w-1/3 flex-shrink-0">
                                            <div className="w-48 h-48 mx-auto rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700 shadow-md border-4 border-teal-100 dark:border-teal-900">
                                                <img 
                                                    src={adviser.image} 
                                                    alt={adviser.name}
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.src = "https://via.placeholder.com/200x200?text=Adviser";
                                                    }}
                                                />
                                            </div>
                                        </div>
                                        <div className="md:w-2/3 text-center md:text-left">
                                            <h3 className="text-2xl font-bold mb-1">{adviser.name}</h3>
                                            <p className="text-teal-600 dark:text-teal-400 font-medium mb-4">{adviser.role}</p>
                                            <p className="text-gray-600 dark:text-gray-300 mb-4">{adviser.bio}</p>
                                            <p className="text-gray-600 dark:text-gray-400 text-sm italic mb-4">Specialization: {adviser.specialization}</p>
                                            <div className="flex justify-center md:justify-start">
                                                <Button 
                                                    variant="outline" 
                                                    size="sm"
                                                    asChild
                                                    className="flex items-center gap-2"
                                                >
                                                    <a href={`mailto:${adviser.email}`}>
                                                        <Mail className="w-4 h-4" />
                                                        Contact Adviser
                                                    </a>
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Team Members Section */}
                        <div className="mb-16">
                            <h2 className="text-2xl font-bold mb-2 text-center">Development Team</h2>
                            <p className="text-center text-gray-600 dark:text-gray-300 mb-8 max-w-2xl mx-auto">
                                Our three-person development team combines expertise in blockchain, full-stack development, 
                                and UI/UX design to create ProcuChain's innovative procurement system
                            </p>
                            
                            <div className="flex justify-center">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl">
                                    {teamMembers.map((member, index) => (
                                        <div
                                            key={index}
                                            className="transition-transform duration-300 hover:-translate-y-2"
                                        >
                                            <Card className="overflow-hidden bg-white dark:bg-gray-800 hover:shadow-xl transition-shadow duration-300 h-full border-teal-100 dark:border-teal-900/40">
                                                <div className="flex flex-col h-full">
                                                    <div className="bg-gradient-to-br from-teal-400/10 to-blue-400/10 dark:from-teal-900/20 dark:to-blue-900/20 p-6 flex items-center justify-center">
                                                        <div className="w-32 h-32 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700 shadow-md border-2 border-teal-100 dark:border-teal-900">
                                                            <img 
                                                                src={member.image} 
                                                                alt={member.name}
                                                                className="w-full h-full object-cover"
                                                                onError={(e) => {
                                                                    e.currentTarget.src = `https://ui-avatars.com/api/?name=${member.name.replace(' ', '+')}&background=0D9488&color=fff&size=200`;
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                    <CardContent className="p-6 flex flex-col flex-grow">
                                                        <div className="mb-auto">
                                                            <h3 className="text-xl font-bold text-center mb-1">{member.name}</h3>
                                                            <div className="mb-4 text-center">
                                                                <Badge className="bg-teal-100 dark:bg-teal-900/50 text-teal-800 dark:text-teal-200 border-none">
                                                                    {member.role}
                                                                </Badge>
                                                            </div>
                                                            <p className="text-sm text-gray-600 dark:text-gray-300 mb-6">{member.bio}</p>
                                                            
                                                            <div className="mb-6">
                                                                <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Key Responsibilities</h4>
                                                                <div className="flex flex-wrap gap-2">
                                                                    {member.responsibilities.map((resp, idx) => (
                                                                        <Badge key={idx} variant="secondary" className="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                                            {resp}
                                                                        </Badge>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div className="flex justify-center gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                                                            <a 
                                                                href={member.github}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="p-2 bg-gray-100 dark:bg-gray-700 rounded-full hover:bg-teal-100 hover:text-teal-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400 transition-all hover:scale-110"
                                                            >
                                                                <Github className="w-4 h-4" />
                                                            </a>
                                                            <a 
                                                                href={member.linkedin}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="p-2 bg-gray-100 dark:bg-gray-700 rounded-full hover:bg-teal-100 hover:text-teal-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400 transition-all hover:scale-110"
                                                            >
                                                                <Linkedin className="w-4 h-4" />
                                                            </a>
                                                            <a 
                                                                href={`mailto:${member.email}`}
                                                                className="p-2 bg-gray-100 dark:bg-gray-700 rounded-full hover:bg-teal-100 hover:text-teal-600 dark:hover:bg-teal-900/30 dark:hover:text-teal-400 transition-all hover:scale-110"
                                                            >
                                                                <Mail className="w-4 h-4" />
                                                            </a>
                                                        </div>
                                                    </CardContent>
                                                </div>
                                            </Card>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Acknowledgments Section */}
                        <div className="mb-16">
                            <h2 className="text-2xl font-bold mb-8 text-center">Acknowledgments</h2>
                            
                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-8">
                                <p className="text-gray-600 dark:text-gray-300 mb-6 text-center">
                                    We extend our sincere gratitude to the following individuals and institutions
                                    for their invaluable support and guidance throughout our capstone project journey.
                                </p>

                                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                                    <div className="text-center p-5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 mb-4">
                                            <GraduationCap className="w-6 h-6" />
                                        </div>
                                        <h3 className="text-lg font-bold mb-3">Faculty Support</h3>
                                        <ul className="space-y-2 text-gray-600 dark:text-gray-300">
                                            <li>Dr. Sheryl Mae D. Laines - Department Chair</li>
                                            <li>Prof. Maria Delgado - Research Coordinator</li>
                                            <li>Prof. John Marquez - Technical Adviser</li>
                                        </ul>
                                    </div>
                                    
                                    <div className="text-center p-5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 mb-4">
                                            <BookOpen className="w-6 h-6" />
                                        </div>
                                        <h3 className="text-lg font-bold mb-3">Institutional Support</h3>
                                        <ul className="space-y-2 text-gray-600 dark:text-gray-300">
                                            <li>Mindoro State University - Bongabong Campus</li>
                                            <li>College of Computer Studies</li>
                                            <li>Information Technology Department</li>
                                        </ul>
                                    </div>
                                    
                                    <div className="text-center p-5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 mb-4">
                                            <Users className="w-6 h-6" />
                                        </div>
                                        <h3 className="text-lg font-bold mb-3">External Partners</h3>
                                        <ul className="space-y-2 text-gray-600 dark:text-gray-300">
                                            <li>Local Government of Gloria</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Call to Action */}
                        <div className="text-center">
                            <Button 
                                asChild
                                className="bg-teal-600 hover:bg-teal-700 text-white group"
                            >
                                <a href={route('contact')}>
                                    Contact Our Team
                                    <ExternalLink className="ml-2 w-4 h-4 group-hover:translate-x-1 transition-transform" />
                                </a>
                            </Button>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}