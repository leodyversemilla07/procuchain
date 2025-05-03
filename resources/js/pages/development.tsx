import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { 
  CheckCircle, 
  ExternalLink, 
  FileText, 
  Users, 
  Book, 
  Code, 
  GitBranch, 
  Calendar
} from 'lucide-react';

export default function Development() {
    return (
        <>
            <Head title="Development Process">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="The development process and methodology behind ProcuChain - a blockchain-powered procurement system." />
            </Head>

            <div className="min-h-screen flex flex-col bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
                <Header />

                <main className="flex-grow pt-24 pb-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="mb-16 text-center">
                            <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                                <Code className="w-6 h-6" />
                            </div>
                            <h1 className="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                                <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                    Development Process
                                </span>
                            </h1>
                            <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-3xl mx-auto">
                                Explore our project methodology, development timeline, and the journey from concept to completion of this capstone project.
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-teal-50 dark:bg-teal-900/30 border-teal-200 dark:border-teal-800">
                                    <GitBranch className="w-3.5 h-3.5 mr-1" />
                                    Agile Methodology
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800">
                                    <Calendar className="w-3.5 h-3.5 mr-1" />
                                    6-Month Timeline
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-purple-50 dark:bg-purple-900/30 border-purple-200 dark:border-purple-800">
                                    <Users className="w-3.5 h-3.5 mr-1" />
                                    1-Person Team
                                </Badge>
                            </div>
                        </div>

                        {/* Timeline Section */}
                        <div className="mb-16">
                            <h2 className="text-3xl font-bold mb-8 text-center">Development Timeline</h2>
                            
                            <div className="relative border-l-2 border-teal-200 dark:border-teal-800 ml-4 md:ml-0 md:mx-auto md:max-w-3xl pl-6 md:pl-0">
                                {[
                                    {
                                        date: "August 2024",
                                        title: "Project Initiation",
                                        description: "Project proposal approved by faculty. Research topics identified and team roles assigned.",
                                        align: "left"
                                    },
                                    {
                                        date: "September 2024",
                                        title: "Requirements Analysis",
                                        description: "Conducted stakeholder interviews and analyzed government procurement processes. Defined system requirements and technical specifications.",
                                        align: "right"
                                    },
                                    {
                                        date: "October 2024",
                                        title: "System Design",
                                        description: "Created system architecture, database schema, and UI/UX wireframes. Selected technology stack and development tools.",
                                        align: "left"
                                    },
                                    {
                                        date: "November-December 2024",
                                        title: "Development Phase",
                                        description: "Implemented core features including blockchain integration, document management, and user authentication. Regular sprint reviews and code refactoring.",
                                        align: "right"
                                    },
                                    {
                                        date: "January 2025",
                                        title: "Testing & Refinement",
                                        description: "Conducted unit testing, integration testing, and user acceptance testing. Fixed bugs and optimized system performance.",
                                        align: "left"
                                    },
                                    {
                                        date: "February-April 2025",
                                        title: "Deployment & Documentation",
                                        description: "System deployment, comprehensive documentation preparation, and final presentation to faculty and stakeholders.",
                                        align: "right"
                                    }
                                ].map((item, index) => (
                                    <div 
                                        key={index}
                                        className={`mb-12 md:flex ${item.align === 'right' ? 'md:flex-row-reverse' : ''}`}
                                    >
                                        {/* Timeline dot */}
                                        <div className="absolute left-[-9px] md:static md:mx-4 flex items-center justify-center">
                                            <div className="h-4 w-4 rounded-full bg-teal-500 dark:bg-teal-400"></div>
                                        </div>
                                        
                                        <div className={`md:w-1/2 ${item.align === 'right' ? 'md:text-right' : ''}`}>
                                            <h3 className="text-lg font-semibold text-teal-600 dark:text-teal-400">{item.date}</h3>
                                            <h4 className="font-bold mb-1">{item.title}</h4>
                                            <p className="text-gray-600 dark:text-gray-300">{item.description}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Methodology and Tools */}
                        <div className="mb-16">
                            <h2 className="text-3xl font-bold mb-8 text-center">Development Approach</h2>
                            <Tabs defaultValue="methodology" className="w-full">
                                <TabsList className="grid w-full grid-cols-3 mb-8">
                                    <TabsTrigger value="methodology">Methodology</TabsTrigger>
                                    <TabsTrigger value="tools">Tools & Technologies</TabsTrigger>
                                    <TabsTrigger value="challenges">Challenges & Solutions</TabsTrigger>
                                </TabsList>
                                
                                <TabsContent value="methodology">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Agile Development Methodology</CardTitle>
                                            <CardDescription>
                                                Our development approach combined Agile principles with academic project management
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div className="space-y-4">
                                                    <h3 className="font-semibold text-lg">Key Methodology Components</h3>
                                                    <ul className="space-y-3">
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                            <div>
                                                                <span className="font-medium">2-Week Sprint Cycles</span>
                                                                <p className="text-sm text-gray-600 dark:text-gray-300">Iterative development with bi-weekly goals and demonstrations</p>
                                                            </div>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                            <div>
                                                                <span className="font-medium">Feature-Driven Development</span>
                                                                <p className="text-sm text-gray-600 dark:text-gray-300">Focused on completing functional features in each sprint</p>
                                                            </div>
                                                        </li>
                                                        <li className="flex items-start">
                                                            <CheckCircle className="w-5 h-5 text-teal-500 mr-3 flex-shrink-0 mt-0.5" />
                                                            <div>
                                                                <span className="font-medium">Weekly Stand-ups</span>
                                                                <p className="text-sm text-gray-600 dark:text-gray-300">Regular progress tracking and obstacle removal</p>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg">
                                                    <h3 className="font-semibold mb-4">Development Workflow</h3>
                                                    <ol className="space-y-2 list-decimal pl-5 text-gray-600 dark:text-gray-300">
                                                        <li>Requirement gathering from stakeholder interviews</li>
                                                        <li>Feature prioritization using MoSCoW method</li>
                                                        <li>Sprint planning with task assignment</li>
                                                        <li>Collaborative development with daily check-ins</li>
                                                        <li>Code reviews and quality assurance</li>
                                                        <li>Bi-weekly demonstrations to advisors</li>
                                                        <li>Retrospective and process improvement</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                                
                                <TabsContent value="tools">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Development Tools & Technologies</CardTitle>
                                            <CardDescription>
                                                The software, frameworks, and tools that powered our development process
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                                    <h3 className="font-semibold mb-3 text-lg">Frontend Development</h3>
                                                    <ul className="space-y-2">
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">React with TypeScript</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">Tailwind CSS</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">Inertia.js</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">Vite</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                                    <h3 className="font-semibold mb-3 text-lg">Backend Development</h3>
                                                    <ul className="space-y-2">
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">Laravel PHP</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">MySQL Database</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">MultiChain API</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">REST API Design</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                                    <h3 className="font-semibold mb-3 text-lg">Project Management</h3>
                                                    <ul className="space-y-2">
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">GitHub</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">Jira for Task Tracking</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">Figma for UI Design</span>
                                                        </li>
                                                        <li className="flex items-center">
                                                            <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                            <span className="text-gray-600 dark:text-gray-300">Discord & Slack</span>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                                
                                <TabsContent value="challenges">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Development Challenges & Solutions</CardTitle>
                                            <CardDescription>
                                                Key obstacles we encountered and how we overcame them
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-6">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                                        <h3 className="font-semibold mb-3 text-lg">Blockchain Integration</h3>
                                                        <div className="space-y-3">
                                                            <div>
                                                                <h4 className="font-medium text-red-600 dark:text-red-400">Challenge:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Integrating MultiChain with Laravel and establishing efficient document verification workflows.
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <h4 className="font-medium text-green-600 dark:text-green-400">Solution:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Created a custom MultiChain client service in PHP with comprehensive error handling and transaction management.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                                        <h3 className="font-semibold mb-3 text-lg">Performance Optimization</h3>
                                                        <div className="space-y-3">
                                                            <div>
                                                                <h4 className="font-medium text-red-600 dark:text-red-400">Challenge:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Document upload and verification processes were initially slow with large files.
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <h4 className="font-medium text-green-600 dark:text-green-400">Solution:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Implemented chunk-based file uploads, parallel processing, and optimized the verification algorithm.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                                        <h3 className="font-semibold mb-3 text-lg">User Experience</h3>
                                                        <div className="space-y-3">
                                                            <div>
                                                                <h4 className="font-medium text-red-600 dark:text-red-400">Challenge:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Creating an intuitive interface for users with varying technical expertise.
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <h4 className="font-medium text-green-600 dark:text-green-400">Solution:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Conducted user testing with procurement officers and implemented progressive disclosure patterns.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                                        <h3 className="font-semibold mb-3 text-lg">Security Implementation</h3>
                                                        <div className="space-y-3">
                                                            <div>
                                                                <h4 className="font-medium text-red-600 dark:text-red-400">Challenge:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Implementing robust security while maintaining system usability.
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <h4 className="font-medium text-green-600 dark:text-green-400">Solution:</h4>
                                                                <p className="text-gray-600 dark:text-gray-300 text-sm">
                                                                    Developed a role-based access control system with granular permissions and security logging.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                            </Tabs>
                        </div>

                        {/* Team Structure */}
                        <div className="mb-16">
                            <h2 className="text-3xl font-bold mb-8 text-center">Team Structure & Roles</h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-xl">Development Team Structure</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-6">
                                            <div className="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg">
                                                <ul className="space-y-4">
                                                    <li className="flex items-start">
                                                        <Users className="w-5 h-5 text-teal-500 mr-3 mt-0.5" />
                                                        <div>
                                                            <h4 className="font-medium">One-Person Development Team</h4>
                                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                Responsible for all aspects of development including full-stack implementation, blockchain integration, UI/UX design, and documentation
                                                            </p>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-xl">Team Collaboration Approach</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-6">
                                            <div className="bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg">
                                                <ul className="space-y-4">
                                                    <li className="flex items-start">
                                                        <CheckCircle className="w-5 h-5 text-teal-500 mr-3 mt-0.5" />
                                                        <div>
                                                            <h4 className="font-medium">Pair Programming</h4>
                                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                Regular pair programming sessions for complex features and knowledge sharing
                                                            </p>
                                                        </div>
                                                    </li>
                                                    <li className="flex items-start">
                                                        <CheckCircle className="w-5 h-5 text-teal-500 mr-3 mt-0.5" />
                                                        <div>
                                                            <h4 className="font-medium">Code Reviews</h4>
                                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                All code changes reviewed by at least one team member before merging
                                                            </p>
                                                        </div>
                                                    </li>
                                                    <li className="flex items-start">
                                                        <CheckCircle className="w-5 h-5 text-teal-500 mr-3 mt-0.5" />
                                                        <div>
                                                            <h4 className="font-medium">Knowledge Rotation</h4>
                                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                                Team members rotated responsibilities to ensure shared understanding
                                                            </p>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        {/* Project Documentation */}
                        <div className="mb-16">
                            <h2 className="text-3xl font-bold mb-8 text-center">Project Documentation</h2>
                            
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <Card className="bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/30 dark:to-blue-900/30 shadow-sm">
                                    <CardContent className="p-6">
                                        <div className="h-12 w-12 rounded-full bg-teal-100 dark:bg-teal-900/50 flex items-center justify-center mb-4">
                                            <Code className="h-6 w-6 text-teal-600 dark:text-teal-400" />
                                        </div>
                                        <h3 className="text-xl font-bold mb-2">Technical Documentation</h3>
                                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                                            API references, system architecture diagrams, and integration guides for developers.
                                        </p>
                                        <Button asChild variant="outline" className="w-full">
                                            <a href={route('documentation')}>
                                                View Documentation
                                                <FileText className="ml-2 h-4 w-4" />
                                            </a>
                                        </Button>
                                    </CardContent>
                                </Card>

                                <Card className="bg-gradient-to-br from-purple-50 to-blue-50 dark:from-purple-900/30 dark:to-blue-900/30 shadow-sm">
                                    <CardContent className="p-6">
                                        <div className="h-12 w-12 rounded-full bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center mb-4">
                                            <Book className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                        </div>
                                        <h3 className="text-xl font-bold mb-2">Research Methodology</h3>
                                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                                            Research approaches, data collection methods, and analysis techniques used.
                                        </p>
                                        <Button asChild variant="outline" className="w-full">
                                            <a href={route('documentation')}>
                                                Read Research Paper
                                                <ExternalLink className="ml-2 h-4 w-4" />
                                            </a>
                                        </Button>
                                    </CardContent>
                                </Card>

                                <Card className="bg-gradient-to-br from-green-50 to-teal-50 dark:from-green-900/30 dark:to-teal-900/30 shadow-sm">
                                    <CardContent className="p-6">
                                        <div className="h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/50 flex items-center justify-center mb-4">
                                            <Users className="h-6 w-6 text-green-600 dark:text-green-400" />
                                        </div>
                                        <h3 className="text-xl font-bold mb-2">User Guides</h3>
                                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                                            Step-by-step tutorials, user manuals, and training materials for system users.
                                        </p>
                                        <Button asChild variant="outline" className="w-full">
                                            <a href={route('documentation')}>
                                                Access User Guides
                                                <ExternalLink className="ml-2 h-4 w-4" />
                                            </a>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        {/* Future Development */}
                        <Card className="mb-16">
                            <CardHeader>
                                <CardTitle className="text-2xl">Future Development Roadmap</CardTitle>
                                <CardDescription>
                                    Our vision for the continued evolution and enhancement of the ProcuChain platform
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                            <h3 className="font-semibold mb-3 text-lg">Phase 1: Q3 2025</h3>
                                            <ul className="space-y-2">
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Mobile application release</span>
                                                </li>
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Advanced analytics dashboard</span>
                                                </li>
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Expanded API integration options</span>
                                                </li>
                                            </ul>
                                        </div>
                                        <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                            <h3 className="font-semibold mb-3 text-lg">Phase 2: Q1 2026</h3>
                                            <ul className="space-y-2">
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">AI-powered fraud detection</span>
                                                </li>
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Multi-agency collaboration tools</span>
                                                </li>
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Smart contract implementation</span>
                                                </li>
                                            </ul>
                                        </div>
                                        <div className="bg-white dark:bg-gray-800/50 p-6 rounded-lg shadow-sm">
                                            <h3 className="font-semibold mb-3 text-lg">Long-term Vision</h3>
                                            <ul className="space-y-2">
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Nationwide implementation</span>
                                                </li>
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Integration with national budget systems</span>
                                                </li>
                                                <li className="flex items-center">
                                                    <CheckCircle className="w-4 h-4 text-teal-500 mr-2" />
                                                    <span className="text-gray-600 dark:text-gray-300">Open data initiatives</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div className="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 mt-8">
                                        <h3 className="font-semibold mb-4 text-lg">Get Involved in Future Development</h3>
                                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                                            We welcome contributions, feedback, and collaboration on the future development of ProcuChain. 
                                            If you're interested in contributing to the project or have suggestions for future features, 
                                            please reach out to our development team.
                                        </p>
                                        <Button asChild variant="outline" className="mt-2">
                                            <a href={route('contact')}>
                                                Contact Development Team
                                                <ExternalLink className="ml-2 h-4 w-4" />
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Call to Action */}
                        <div className="bg-gradient-to-r from-teal-600 to-teal-500 rounded-xl text-white p-8 md:p-12 text-center">
                            <h2 className="text-3xl font-bold mb-4">Interested in Our Development Process?</h2>
                            <p className="text-xl opacity-90 max-w-2xl mx-auto mb-8">
                                Learn more about our methodology, access our documentation, or contact us for information about collaborating on future projects.
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Button asChild size="lg" className="bg-white text-teal-600 hover:bg-gray-100">
                                    <a href={route('documentation')}>
                                        <Book className="mr-2 h-5 w-5" />
                                        View Documentation
                                    </a>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="border-white text-white hover:bg-teal-700">
                                    <a href={route('contact')}>
                                        Contact Team
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}