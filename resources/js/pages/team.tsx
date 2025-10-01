import Footer from '@/components/footer';
import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/react';
import { Facebook, Github, Mail } from 'lucide-react';

export default function Team() {
    const teamMembers = [
        {
            name: 'Mr. Uriel M. Melendres',
            role: 'Project Adviser',
            bio: 'Guided the team throughout the development process, providing expertise in blockchain technology and research methodology.',
            responsibilities: ['Project Guidance', 'Research Methodology', 'Team Mentorship'],
            image: '/images/team/adviser.jpg',
            github: 'https://github.com/urielmelendres',
            email: 'uriel.melendres@minsu.edu.ph',
            facebook: 'https://www.facebook.com/um1994',
        },
        {
            name: 'Leodyver S. Semilla',
            role: 'Team Leader / Developer',
            bio: 'Led the team and completed the full development of ProcuChain, implementing blockchain integration, system architecture, frontend, and backend development.',
            responsibilities: ['Team Leadership', 'Full System Development', 'Blockchain Integration', 'System Architecture'],
            image: '/images/team/leodyver.jpg',
            github: 'https://github.com/leodyversemilla07',
            email: 'semilla.leodyver@minsu.edu.ph',
            facebook: 'https://www.facebook.com/ldyvrsmll07',
        },
        {
            name: 'Bryle F. Maamo',
            role: 'Lead Researcher / Documentation',
            bio: 'Led the research efforts and authored the research paper, focusing on procurement processes and blockchain implementation theory.',
            responsibilities: ['Research Paper Writing', 'Literature Review', 'Methodology Development', 'Documentation'],
            image: '/images/team/bryle.png',
            github: 'https://github.com/Maamo16',
            email: 'maamo.bryle@minsu.edu.ph',
            facebook: 'https://www.facebook.com/bryle.famorcanmaamo',
        },
        {
            name: 'Adrian P. Gupit',
            role: 'Research Support / Assistant',
            bio: 'Provided support to the research efforts, assisting in data gathering and documentation for the research paper.',
            responsibilities: ['Research Assistance', 'Data Collection', 'Documentation Support', 'Reference Management'],
            image: '/images/team/adrian.jpg',
            github: 'https://github.com/adriangupit',
            email: 'adrian.gupit@minsu.edu.ph',
            facebook: 'https://www.facebook.com/adrian.gupit.71',
        },
    ];

    return (
        <>
            <Head title="Our Team">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta
                    name="description"
                    content="Meet the talented professionals behind ProcuChain - revolutionizing government procurement through blockchain technology and innovative solutions."
                />
            </Head>{' '}
            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <Header />

                <main className="flex-grow pt-16 pb-8 sm:pt-20 sm:pb-12 md:pt-24 md:pb-16 lg:pt-32 lg:pb-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
                        {/* Hero Section */}
                        <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
                            <h1 className="mb-3 text-center text-2xl font-medium sm:mb-4 sm:text-3xl md:mb-6 md:text-4xl">Our Team</h1>
                            <p className="text-muted-foreground mx-auto max-w-xl px-2 text-center text-sm sm:px-4 sm:text-base md:px-0">
                                Meet the dedicated individuals behind ProcuChain, working together to revolutionize government procurement.
                            </p>
                        </div>
                        {/* Team Members Section */}{' '}
                        <div className="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 md:gap-8">
                            {teamMembers.map((member, index) => (
                                <Card key={index} className="bg-muted border-0">
                                    <CardContent className="p-4 sm:p-6 md:p-8">
                                        <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start sm:gap-6 md:gap-8">
                                            <div className="bg-card h-20 w-20 flex-shrink-0 overflow-hidden rounded-full shadow-sm sm:h-24 sm:w-24 md:h-28 md:w-28">
                                                <img
                                                    src={member.image}
                                                    alt={member.name}
                                                    className="h-full w-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.src = `https://ui-avatars.com/api/?name=${member.name.replace(' ', '+')}&background=0D9488&color=fff&size=200`;
                                                    }}
                                                />
                                            </div>
                                            <div className="flex-1 text-center sm:text-left">
                                                <h3 className="mb-1 text-base font-medium sm:mb-2 sm:text-lg md:text-xl">{member.name}</h3>{' '}
                                                <Badge className="bg-primary/10 text-primary mb-2 border-none text-xs sm:mb-3">{member.role}</Badge>
                                                <p className="text-muted-foreground mb-3 text-xs sm:mb-4 sm:text-sm md:text-base">{member.bio}</p>
                                                <div className="mb-3 flex flex-wrap gap-1.5 sm:mb-4 sm:gap-2">
                                                    {member.responsibilities.map((resp, idx) => (
                                                        <Badge key={idx} variant="secondary" className="bg-card text-muted-foreground text-xs">
                                                            {resp}
                                                        </Badge>
                                                    ))}
                                                </div>
                                                <div className="border-border flex justify-center gap-3 border-t pt-3 sm:justify-start sm:pt-4">
                                                    {' '}
                                                    {member.github && (
                                                        <a
                                                            href={member.github}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-muted-foreground hover:text-primary transition-colors"
                                                            aria-label={`${member.name}'s Github Profile`}
                                                        >
                                                            <Github className="h-4 w-4 sm:h-5 sm:w-5" />
                                                        </a>
                                                    )}
                                                    {member.email && (
                                                        <a
                                                            href={`mailto:${member.email}`}
                                                            className="text-muted-foreground hover:text-primary transition-colors"
                                                            aria-label={`Email ${member.name}`}
                                                        >
                                                            <Mail className="h-4 w-4 sm:h-5 sm:w-5" />
                                                        </a>
                                                    )}
                                                    {member.facebook && (
                                                        <a
                                                            href={member.facebook}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-muted-foreground hover:text-primary transition-colors"
                                                            aria-label={`${member.name}'s Facebook Profile`}
                                                        >
                                                            <Facebook className="h-4 w-4 sm:h-5 sm:w-5" />
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
