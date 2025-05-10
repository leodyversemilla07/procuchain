import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { Mail, Github, Facebook } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

export default function Team() {
    const teamMembers = [
        {
            name: "Mr. Uriel M. Melendres",
            role: "Project Adviser",
            bio: "Guided the team throughout the development process, providing expertise in blockchain technology and research methodology.",
            responsibilities: ["Project Guidance", "Research Methodology", "Team Mentorship"],
            image: "/images/team/adviser.jpg",
            github: "https://github.com/urielmelendres",
            email: "uriel.melendres@minsu.edu.ph",
            facebook: "https://www.facebook.com/um1994",
        },
        {
            name: "Leodyver S. Semilla",
            role: "Team Leader / Developer",
            bio: "Led the team and completed the full development of ProcuChain, implementing blockchain integration, system architecture, frontend, and backend development.",
            responsibilities: ["Team Leadership", "Full System Development", "Blockchain Integration", "System Architecture"],
            image: "/images/team/leodyver.jpg",
            github: "https://github.com/leodyversemilla07",
            email: "semilla.leodyver@minsu.edu.ph",
            facebook: "https://www.facebook.com/ldyvrsmll07",
        },
        {
            name: "Bryle F. Maamo",
            role: "Lead Researcher / Documentation",
            bio: "Led the research efforts and authored the research paper, focusing on procurement processes and blockchain implementation theory.",
            responsibilities: ["Research Paper Writing", "Literature Review", "Methodology Development", "Documentation"],
            image: "/images/team/bryle.png",
            github: "https://github.com/Maamo16",
            email: "maamo.bryle@minsu.edu.ph",
            facebook: "https://www.facebook.com/bryle.famorcanmaamo",
        },
        {
            name: "Adrian P. Gupit",
            role: "Research Support / Assistant",
            bio: "Provided support to the research efforts, assisting in data gathering and documentation for the research paper.",
            responsibilities: ["Research Assistance", "Data Collection", "Documentation Support", "Reference Management"],
            image: "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?fm=jpg&q=60&w=3000&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8NHx8bWFsZSUyMHByb2ZpbGV8ZW58MHx8MHx8fDA%3D",
            github: "https://github.com/adriangupit",
            email: "adrian.gupit@minsu.edu.ph",
            facebook: "https://www.facebook.com/adrian.gupit.71",
        },
    ];

    return (
        <>
            <Head title="Our Team">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="Meet the talented professionals behind ProcuChain - revolutionizing government procurement through blockchain technology and innovative solutions." />
            </Head>

            <div className="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-white">
                <Header />

                <main className="flex-grow pt-16 sm:pt-20 md:pt-24 lg:pt-32 pb-8 sm:pb-12 md:pb-16 lg:pb-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        {/* Hero Section */}
                        <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
                            <h1 className="text-2xl sm:text-3xl md:text-4xl font-medium mb-3 sm:mb-4 md:mb-6 text-center">
                                Our Team
                            </h1>
                            <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400 text-center max-w-xl mx-auto px-2 sm:px-4 md:px-0">
                                Meet the dedicated individuals behind ProcuChain, working together to revolutionize government procurement.
                            </p>
                        </div>

                        {/* Team Members Section */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 md:gap-8">
                            {teamMembers.map((member, index) => (
                                <Card key={index} className="bg-gray-50 dark:bg-gray-900/50 border-0">
                                    <CardContent className="p-4 sm:p-6 md:p-8">
                                        <div className="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 md:gap-8">
                                            <div className="w-20 h-20 sm:w-24 sm:h-24 md:w-28 md:h-28 rounded-full overflow-hidden bg-white dark:bg-gray-800 shadow-sm flex-shrink-0">
                                                <img
                                                    src={member.image}
                                                    alt={member.name}
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.src = `https://ui-avatars.com/api/?name=${member.name.replace(' ', '+')}&background=0D9488&color=fff&size=200`;
                                                    }}
                                                />
                                            </div>
                                            <div className="flex-1 text-center sm:text-left">
                                                <h3 className="text-base sm:text-lg md:text-xl font-medium mb-1 sm:mb-2">{member.name}</h3>
                                                <Badge className="text-xs bg-teal-100 dark:bg-teal-900/50 text-teal-800 dark:text-teal-200 border-none mb-2 sm:mb-3">
                                                    {member.role}
                                                </Badge>
                                                <p className="text-xs sm:text-sm md:text-base text-gray-600 dark:text-gray-400 mb-3 sm:mb-4">{member.bio}</p>
                                                
                                                <div className="flex flex-wrap gap-1.5 sm:gap-2 mb-3 sm:mb-4">
                                                    {member.responsibilities.map((resp, idx) => (
                                                        <Badge
                                                            key={idx}
                                                            variant="secondary"
                                                            className="text-xs bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300"
                                                        >
                                                            {resp}
                                                        </Badge>
                                                    ))}
                                                </div>

                                                <div className="flex justify-center sm:justify-start gap-3 pt-3 sm:pt-4 border-t border-gray-200 dark:border-gray-800">
                                                    {member.github && (
                                                        <a
                                                            href={member.github}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors"
                                                            aria-label={`${member.name}'s Github Profile`}
                                                        >
                                                            <Github className="w-4 h-4 sm:w-5 sm:h-5" />
                                                        </a>
                                                    )}
                                                    {member.email && (
                                                        <a
                                                            href={`mailto:${member.email}`}
                                                            className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors"
                                                            aria-label={`Email ${member.name}`}
                                                        >
                                                            <Mail className="w-4 h-4 sm:w-5 sm:h-5" />
                                                        </a>
                                                    )}
                                                    {member.facebook && (
                                                        <a
                                                            href={member.facebook}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-gray-600 dark:text-gray-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors"
                                                            aria-label={`${member.name}'s Facebook Profile`}
                                                        >
                                                            <Facebook className="w-4 h-4 sm:w-5 sm:h-5" />
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}
