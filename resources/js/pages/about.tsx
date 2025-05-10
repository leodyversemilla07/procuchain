import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CheckCircle, FileText, Database, Server, BarChart2 } from 'lucide-react';

export default function About() {
  return (
    <>
      <Head title="About">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <meta name="description" content="Learn about ProcuChain - an innovative blockchain-based system designed to bring transparency and efficiency to government procurement processes." />
      </Head>
      <div className="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-white">
        <Header />

        <main className="flex-grow pt-16 sm:pt-20 md:pt-24 lg:pt-32 pb-8 sm:pb-12 md:pb-16 lg:pb-20">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
            {/* Hero Section */}
            <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
              <h1 className="text-2xl sm:text-3xl md:text-4xl font-medium mb-3 sm:mb-4 md:mb-6 text-center">
                About ProcuChain
              </h1>
              <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400 text-center max-w-xl mx-auto px-2 sm:px-4 md:px-0">
                A blockchain-powered solution revolutionizing government procurement through transparency and efficiency.
              </p>
            </div>

            {/* Overview Section */}
            <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
              <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                <CardContent className="p-4 sm:p-6 md:p-8">
                  <div className="max-w-3xl mx-auto">
                    <h2 className="text-lg sm:text-xl md:text-2xl font-medium mb-3 sm:mb-4">Project Overview</h2>
                    <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400 mb-3 sm:mb-4">
                      ProcuChain is a capstone project developed by Information Technology student at Mindoro State University - Bongabong Campus.
                      It leverages blockchain technology to address challenges in government procurement processes.
                    </p>
                    <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400">
                      Our system creates an immutable record of procurement documents and activities,
                      ensuring transparency, preventing fraud, and establishing a verifiable audit trail
                      that can be trusted by all stakeholders.
                    </p>
                  </div>
                </CardContent>
              </Card>
            </div>

            {/* Problem & Solution */}
            <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20 grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
              <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                <CardContent className="p-4 sm:p-6 md:p-8">
                  <div className="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                    <div className="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                      <FileText className="w-4 h-4 sm:w-5 sm:h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <h2 className="text-lg sm:text-xl md:text-2xl font-medium">The Problem</h2>
                  </div>
                  <ul className="space-y-2 sm:space-y-3">
                    {[
                      "Lack of transparency in government procurement processes",
                      "Vulnerability to document tampering and fraud",
                      "Inefficient document tracking and verification",
                      "Limited public access to procurement information",
                      "Challenges in establishing accountability"
                    ].map((item, index) => (
                      <li key={index} className="flex items-start text-sm sm:text-base text-gray-600 dark:text-gray-400">
                        <div className="mr-2 sm:mr-3 mt-1 text-red-500">•</div>
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>

              <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                <CardContent className="p-4 sm:p-6 md:p-8">
                  <div className="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                    <div className="p-2 bg-teal-100 dark:bg-teal-900/30 rounded-lg">
                      <CheckCircle className="w-4 h-4 sm:w-5 sm:h-5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <h2 className="text-lg sm:text-xl md:text-2xl font-medium">Our Solution</h2>
                  </div>
                  <ul className="space-y-2 sm:space-y-3">
                    {[
                      "Blockchain-based document verification and storage",
                      "Immutable audit trail for all procurement activities",
                      "Secure, role-based access control system",
                      "Transparent tracking of procurement stages",
                      "Digital verification of document authenticity"
                    ].map((item, index) => (
                      <li key={index} className="flex items-start text-sm sm:text-base text-gray-600 dark:text-gray-400">
                        <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 sm:mr-3 mt-1 text-teal-500" />
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
            </div>

            {/* Technologies Used */}
            <div className="mb-8 sm:mb-12 md:mb-16 lg:mb-20">
              <h2 className="text-lg sm:text-xl md:text-2xl font-medium mb-4 sm:mb-6 md:mb-8 text-center">Technologies Used</h2>

              <Tabs defaultValue="blockchain" className="w-full max-w-4xl mx-auto">
                <TabsList className="grid w-full grid-cols-3 mb-4 sm:mb-6">
                  <TabsTrigger value="blockchain" className="text-xs sm:text-sm">Blockchain</TabsTrigger>
                  <TabsTrigger value="frontend" className="text-xs sm:text-sm">Frontend</TabsTrigger>
                  <TabsTrigger value="backend" className="text-xs sm:text-sm">Backend</TabsTrigger>
                </TabsList>

                <TabsContent value="blockchain">
                  <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                    <CardContent className="p-4 sm:p-6 md:p-8">
                      <div className="flex flex-col md:flex-row gap-4 sm:gap-6 md:gap-8">
                        <div className="flex justify-center">
                          <div className="p-3 sm:p-4 bg-white dark:bg-gray-800 rounded-lg w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 flex items-center justify-center">
                            <Database className="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 text-teal-600 dark:text-teal-400" />
                          </div>
                        </div>
                        <div className="flex-1">
                          <h3 className="text-base sm:text-lg md:text-xl font-medium mb-2 sm:mb-3">Blockchain Infrastructure</h3>
                          <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400 mb-3 sm:mb-4">
                            ProcuChain utilizes MultiChain, a permission-based blockchain platform optimized for rapid development
                            and deployment. This enterprise-focused blockchain solution provides the security and immutability needed
                            for government procurement processes while allowing fine-grained permission control.
                          </p>
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div className="flex items-start">
                              <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-500 mr-2 mt-1" />
                              <div>
                                <h4 className="text-xs sm:text-sm font-medium">Document Hashing</h4>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Secure cryptographic verification</p>
                              </div>
                            </div>
                            <div className="flex items-start">
                              <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-500 mr-2 mt-1" />
                              <div>
                                <h4 className="text-xs sm:text-sm font-medium">Immutable Ledger</h4>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Tamper-proof record keeping</p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </TabsContent>

                <TabsContent value="frontend">
                  <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                    <CardContent className="p-4 sm:p-6 md:p-8">
                      <div className="flex flex-col md:flex-row gap-4 sm:gap-6 md:gap-8">
                        <div className="flex justify-center">
                          <div className="p-3 sm:p-4 bg-white dark:bg-gray-800 rounded-lg w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 flex items-center justify-center">
                            <BarChart2 className="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 text-teal-600 dark:text-teal-400" />
                          </div>
                        </div>
                        <div className="flex-1">
                          <h3 className="text-base sm:text-lg md:text-xl font-medium mb-2 sm:mb-3">User Interface & Experience</h3>
                          <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400 mb-3 sm:mb-4">
                            The frontend is built using React with TypeScript for type safety, and Tailwind CSS for responsive,
                            modern UI components. Inertia.js bridges the gap between the Laravel backend and React frontend,
                            creating a seamless single-page application experience.
                          </p>
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div className="flex items-start">
                              <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-500 mr-2 mt-1" />
                              <div>
                                <h4 className="text-xs sm:text-sm font-medium">React & TypeScript</h4>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Component-based architecture</p>
                              </div>
                            </div>
                            <div className="flex items-start">
                              <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-500 mr-2 mt-1" />
                              <div>
                                <h4 className="text-xs sm:text-sm font-medium">Tailwind CSS</h4>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Utility-first styling approach</p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </TabsContent>

                <TabsContent value="backend">
                  <Card className="bg-gray-50 dark:bg-gray-900/50 border-0">
                    <CardContent className="p-4 sm:p-6 md:p-8">
                      <div className="flex flex-col md:flex-row gap-4 sm:gap-6 md:gap-8">
                        <div className="flex justify-center">
                          <div className="p-3 sm:p-4 bg-white dark:bg-gray-800 rounded-lg w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 flex items-center justify-center">
                            <Server className="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 text-teal-600 dark:text-teal-400" />
                          </div>
                        </div>
                        <div className="flex-1">
                          <h3 className="text-base sm:text-lg md:text-xl font-medium mb-2 sm:mb-3">Server & Database Architecture</h3>
                          <p className="text-sm sm:text-base text-gray-600 dark:text-gray-400 mb-3 sm:mb-4">
                            Laravel powers the backend, providing robust API development, authentication, and security features.
                            The application uses a hybrid storage approach, with sensitive data in a MySQL database and document
                            verification data stored on the blockchain.
                          </p>
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div className="flex items-start">
                              <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-500 mr-2 mt-1" />
                              <div>
                                <h4 className="text-xs sm:text-sm font-medium">Laravel PHP Framework</h4>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Secure application framework</p>
                              </div>
                            </div>
                            <div className="flex items-start">
                              <CheckCircle className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-500 mr-2 mt-1" />
                              <div>
                                <h4 className="text-xs sm:text-sm font-medium">MySQL Database</h4>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Relational data storage</p>
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
          </div>
        </main>

        <Footer />
      </div>
    </>
  );
}
