import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, ExternalLink, FileText, Lock, LucideIcon, Shield, Users, Zap, Database, Server, BarChart2 } from 'lucide-react';

export default function About() {
  return (
    <>
      <Head title="About">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <meta name="description" content="Learn about ProcuChain - an innovative blockchain-based system designed to bring transparency and efficiency to government procurement processes." />
      </Head>
      <div className="min-h-screen flex flex-col bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
        <Header />

        <main className="flex-grow pt-24 pb-16">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {/* Hero Section */}
            <div className="mb-16 text-center">
              <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                <FileText className="w-6 h-6" />
              </div>
              <h1 className="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                  About ProcuChain
                </span>
              </h1>
              <p className="text-lg md:text-xl text-gray-600 dark:text-gray-300 mb-6 max-w-3xl mx-auto">
                A blockchain-powered solution to enhance transparency, security,
                and efficiency in government procurement processes
              </p>
              <div className="flex flex-wrap justify-center gap-4">
                <Badge variant="outline" className="px-3 py-1 text-sm bg-teal-50 dark:bg-teal-900/30 border-teal-200 dark:border-teal-800">
                  <Shield className="w-3.5 h-3.5 mr-1" />
                  Blockchain Technology
                </Badge>
                <Badge variant="outline" className="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800">
                  <Lock className="w-3.5 h-3.5 mr-1" />
                  Data Integrity
                </Badge>
                <Badge variant="outline" className="px-3 py-1 text-sm bg-purple-50 dark:bg-purple-900/30 border-purple-200 dark:border-purple-800">
                  <Users className="w-3.5 h-3.5 mr-1" />
                  Transparency
                </Badge>
              </div>
            </div>

            {/* Overview Section with Image */}
            <div className="mb-16">
              <div className="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
                <div className="flex flex-col md:flex-row">
                  <div className="md:w-1/2 p-8">
                    <h2 className="text-3xl font-bold mb-4 bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">Project Overview</h2>
                    <p className="text-gray-600 dark:text-gray-300 mb-4">
                      ProcuChain is a capstone project developed by Information Technology students at Mindoro State University.
                      It leverages blockchain technology to address challenges in government procurement processes.
                    </p>
                    <p className="text-gray-600 dark:text-gray-300 mb-4">
                      Our system creates an immutable record of procurement documents and activities,
                      ensuring transparency, preventing fraud, and establishing a verifiable audit trail
                      that can be trusted by all stakeholders.
                    </p>
                    <div className="mt-6 flex flex-wrap gap-3">
                      <Button asChild variant="default" className="bg-teal-600 hover:bg-teal-700 text-white">
                        <a href={route('documentation')}>
                          Explore Documentation
                          <ExternalLink className="w-4 h-4 ml-2" />
                        </a>
                      </Button>
                      <Button asChild variant="outline" className="border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20">
                        <a href={route('team')}>
                          Meet Our Team
                          <Users className="w-4 h-4 ml-2" />
                        </a>
                      </Button>
                    </div>
                  </div>
                  <div className="md:w-1/2 bg-gradient-to-br from-teal-400/10 to-blue-400/10 dark:from-teal-900/20 dark:to-blue-900/20 p-6 flex items-center justify-center">
                    <div className="relative w-full max-w-md rounded-lg overflow-hidden shadow-xl">
                      <div className="aspect-w-16 aspect-h-9">
                        <img
                          src="/images/blockchain-procurement.png"
                          alt="Blockchain-based procurement process visualization"
                          className="w-full h-full object-cover rounded-lg"
                          onError={(e) => {
                            e.currentTarget.src = "https://via.placeholder.com/800x450?text=ProcuChain+Visualization";
                          }}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Problem & Solution */}
            <div className="mb-16 grid grid-cols-1 md:grid-cols-2 gap-8">
              <Card className="bg-white dark:bg-gray-800">
                <CardContent className="p-8">
                  <div className="mb-4 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 inline-block rounded-lg">
                    <FileText className="w-6 h-6" />
                  </div>
                  <h2 className="text-2xl font-bold mb-4">The Problem</h2>
                  <ul className="space-y-3">
                    {[
                      "Lack of transparency in government procurement processes",
                      "Vulnerability to document tampering and fraud",
                      "Inefficient document tracking and verification",
                      "Limited public access to procurement information",
                      "Challenges in establishing accountability"
                    ].map((item, index) => (
                      <li key={index} className="flex items-start text-gray-600 dark:text-gray-300">
                        <div className="mr-3 mt-1 text-red-500">•</div>
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>

              <Card className="bg-white dark:bg-gray-800">
                <CardContent className="p-8">
                  <div className="mb-4 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 p-3 inline-block rounded-lg">
                    <CheckCircle className="w-6 h-6" />
                  </div>
                  <h2 className="text-2xl font-bold mb-4">Our Solution</h2>
                  <ul className="space-y-3">
                    {[
                      "Blockchain-based document verification and storage",
                      "Immutable audit trail for all procurement activities",
                      "Secure, role-based access control system",
                      "Transparent tracking of procurement stages",
                      "Digital verification of document authenticity"
                    ].map((item, index) => (
                      <li key={index} className="flex items-start text-gray-600 dark:text-gray-300">
                        <CheckCircle className="w-4 h-4 mr-3 mt-1 text-green-500" />
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
            </div>

            {/* Project Objectives */}
            <div className="mb-16">
              <h2 className="text-3xl font-bold mb-8 text-center">Project Objectives</h2>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {[
                  {
                    title: "Enhance Transparency",
                    description: "Create a transparent procurement system where all stakeholders can verify document authenticity and track process status in real-time.",
                    icon: Shield,
                    color: "teal"
                  },
                  {
                    title: "Improve Security",
                    description: "Implement blockchain technology to prevent document tampering and create an immutable record of all procurement activities.",
                    icon: Lock,
                    color: "blue"
                  },
                  {
                    title: "Increase Efficiency",
                    description: "Streamline procurement processes through digitization and automation, reducing processing time and administrative burden.",
                    icon: Zap,
                    color: "amber"
                  }
                ].map((objective, index) => (
                  <ObjectiveCard
                    key={index}
                    title={objective.title}
                    description={objective.description}
                    icon={objective.icon}
                    color={objective.color}
                  />
                ))}
              </div>
            </div>

            {/* Technologies Used */}
            <div className="mb-16">
              <h2 className="text-3xl font-bold mb-8 text-center">Technologies Used</h2>

              <Tabs defaultValue="blockchain" className="w-full">
                <TabsList className="grid w-full grid-cols-3 mb-8">
                  <TabsTrigger value="blockchain">Blockchain</TabsTrigger>
                  <TabsTrigger value="frontend">Frontend</TabsTrigger>
                  <TabsTrigger value="backend">Backend & Database</TabsTrigger>
                </TabsList>

                <TabsContent value="blockchain" className="animate-fadeIn">
                  <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row">
                      <div className="md:w-1/3 mb-6 md:mb-0 flex justify-center">
                        <div className="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-full w-48 h-48 flex items-center justify-center">
                          <Database className="w-24 h-24 text-blue-500 dark:text-blue-400" />
                        </div>
                      </div>
                      <div className="md:w-2/3 md:pl-8">
                        <h3 className="text-2xl font-bold mb-4">Blockchain Infrastructure</h3>
                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                          ProcuChain utilizes MultiChain, a permission-based blockchain platform optimized for rapid development
                          and deployment. This enterprise-focused blockchain solution provides the security and immutability needed
                          for government procurement processes while allowing fine-grained permission control.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                          <div className="flex items-start">
                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5" />
                            <div>
                              <h4 className="font-semibold">Document Hashing</h4>
                              <p className="text-sm text-gray-500 dark:text-gray-400">Secure cryptographic verification</p>
                            </div>
                          </div>
                          <div className="flex items-start">
                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5" />
                            <div>
                              <h4 className="font-semibold">Immutable Ledger</h4>
                              <p className="text-sm text-gray-500 dark:text-gray-400">Tamper-proof record keeping</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </TabsContent>

                <TabsContent value="frontend" className="animate-fadeIn">
                  <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row">
                      <div className="md:w-1/3 mb-6 md:mb-0 flex justify-center">
                        <div className="p-4 bg-teal-50 dark:bg-teal-900/20 rounded-full w-48 h-48 flex items-center justify-center">
                          <BarChart2 className="w-24 h-24 text-teal-500 dark:text-teal-400" />
                        </div>
                      </div>
                      <div className="md:w-2/3 md:pl-8">
                        <h3 className="text-2xl font-bold mb-4">User Interface & Experience</h3>
                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                          The frontend is built using React with TypeScript for type safety, and Tailwind CSS for responsive,
                          modern UI components. Inertia.js bridges the gap between the Laravel backend and React frontend,
                          creating a seamless single-page application experience.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                          <div className="flex items-start">
                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5" />
                            <div>
                              <h4 className="font-semibold">React & TypeScript</h4>
                              <p className="text-sm text-gray-500 dark:text-gray-400">Component-based architecture</p>
                            </div>
                          </div>
                          <div className="flex items-start">
                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5" />
                            <div>
                              <h4 className="font-semibold">Tailwind CSS</h4>
                              <p className="text-sm text-gray-500 dark:text-gray-400">Utility-first styling approach</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </TabsContent>

                <TabsContent value="backend" className="animate-fadeIn">
                  <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row">
                      <div className="md:w-1/3 mb-6 md:mb-0 flex justify-center">
                        <div className="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-full w-48 h-48 flex items-center justify-center">
                          <Server className="w-24 h-24 text-purple-500 dark:text-purple-400" />
                        </div>
                      </div>
                      <div className="md:w-2/3 md:pl-8">
                        <h3 className="text-2xl font-bold mb-4">Server & Database Architecture</h3>
                        <p className="text-gray-600 dark:text-gray-300 mb-4">
                          Laravel powers the backend, providing robust API development, authentication, and security features.
                          The application uses a hybrid storage approach, with sensitive data in a MySQL database and document
                          verification data stored on the blockchain.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                          <div className="flex items-start">
                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5" />
                            <div>
                              <h4 className="font-semibold">Laravel PHP Framework</h4>
                              <p className="text-sm text-gray-500 dark:text-gray-400">Secure application framework</p>
                            </div>
                          </div>
                          <div className="flex items-start">
                            <CheckCircle className="w-5 h-5 text-teal-500 mr-2 mt-0.5" />
                            <div>
                              <h4 className="font-semibold">MySQL Database</h4>
                              <p className="text-sm text-gray-500 dark:text-gray-400">Relational data storage</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </TabsContent>
              </Tabs>
            </div>

            {/* Call to Action */}
            <div className="text-center bg-white dark:bg-gray-800 rounded-xl p-8 shadow-sm">
              <h2 className="text-2xl font-bold mb-4">Ready to learn more?</h2>
              <p className="text-gray-600 dark:text-gray-300 mb-6 max-w-2xl mx-auto">
                Explore our documentation to understand how ProcuChain works and the benefits it brings to the procurement process.
              </p>
              <div className="flex flex-col sm:flex-row justify-center gap-4">
                <Button asChild variant="default" className="bg-teal-600 hover:bg-teal-700 text-white">
                  <a href={route('features')}>
                    View Features
                    <Zap className="w-4 h-4 ml-2" />
                  </a>
                </Button>
                <Button asChild variant="outline" className="border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20">
                  <a href={route('documentation')}>
                    Read Documentation
                    <ExternalLink className="w-4 h-4 ml-2" />
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

interface ObjectiveCardProps {
  title: string;
  description: string;
  icon: LucideIcon;
  color: string;
}

function ObjectiveCard({ title, description, icon: Icon, color }: ObjectiveCardProps) {
  const colorClasses = {
    teal: "bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300",
    blue: "bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300",
    amber: "bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300",
  };

  return (
    <Card className="bg-white dark:bg-gray-800 hover:shadow-lg transition-shadow">
      <CardContent className="p-6">
        <div className={`mb-4 p-3 inline-block rounded-lg ${colorClasses[color as keyof typeof colorClasses]}`}>
          <Icon className="w-6 h-6" />
        </div>
        <h3 className="text-xl font-bold mb-2">{title}</h3>
        <p className="text-gray-600 dark:text-gray-300">{description}</p>
      </CardContent>
    </Card>
  );
}
