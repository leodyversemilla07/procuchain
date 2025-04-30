import { Head, Link } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import { Info, Building, FileText, Shield, CheckCircle, Mail, ArrowRight, Globe } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function About() {
  return (
    <>
      <Head title="About ProcuChain - Blockchain-powered Procurement System">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <meta name="description" content="About ProcuChain - Learn about our mission, technology, and approach to blockchain-powered procurement." />
      </Head>
      <div className="min-h-screen flex flex-col bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative overflow-x-hidden">
        <Header />

        <main className="flex-grow mt-[72px] sm:mt-[76px] pb-24">
          <section className="relative">
            {/* Background Patterns */}
            <div className="absolute inset-0 overflow-hidden pointer-events-none">
              <div className="absolute -top-1/2 -right-1/2 w-[100rem] h-[100rem] rounded-full bg-gradient-to-br from-teal-50/40 to-blue-50/40 dark:from-teal-900/20 dark:to-blue-900/20 blur-3xl transform rotate-45"></div>
            </div>

            <div className="container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-16">
              <div className="text-center mb-16">
                <span className="text-teal-500 font-semibold text-lg">Our Story</span>
                <h1 className="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight leading-tight mt-2">
                  <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                    About ProcuChain
                  </span>
                </h1>
                <p className="text-xl md:text-2xl text-gray-600 dark:text-gray-300 leading-relaxed max-w-3xl mx-auto mt-6">
                  Revolutionizing procurement processes through blockchain technology
                </p>
              </div>

              {/* Mission Section */}
              <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 mb-12">
                <div className="flex flex-col md:flex-row gap-8 items-center">
                  <div className="md:w-1/3 flex justify-center">
                    <div className="relative">
                      <div className="absolute inset-0 bg-gradient-to-br from-teal-400/20 to-blue-400/20 rounded-full blur-2xl"></div>
                      <Globe className="w-32 h-32 text-teal-500 relative z-10" />
                    </div>
                  </div>
                  <div className="md:w-2/3">
                    <h2 className="text-2xl font-bold mb-4 flex items-center">
                      <Building className="w-6 h-6 text-teal-500 mr-2" />
                      Our Mission
                    </h2>
                    <p className="text-lg text-gray-600 dark:text-gray-300">
                      ProcuChain is dedicated to revolutionizing procurement processes through blockchain technology,
                      ensuring transparency, security, and efficiency in all procurement activities. We believe in creating
                      a future where government procurement is fully transparent, efficient, and free from corruption.
                    </p>
                  </div>
                </div>
              </div>

              {/* What We Do Section */}
              <div className="bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 rounded-2xl shadow-xl p-8 mb-12">
                <h2 className="text-3xl font-bold mb-8 text-center">What We Do</h2>
                <p className="text-lg text-gray-600 dark:text-gray-300 mb-8 max-w-3xl mx-auto text-center">
                  We provide a comprehensive platform that streamlines the entire procurement lifecycle, from initial
                  requisition to final delivery and payment.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                  {[
                    {
                      icon: <FileText className="w-8 h-8 text-teal-500" />,
                      title: "Document Management",
                      description: "Immutable record-keeping for all procurement activities"
                    },
                    {
                      icon: <Shield className="w-8 h-8 text-teal-500" />,
                      title: "Enhanced Security",
                      description: "Transparent and accountable procurement processes"
                    },
                    {
                      icon: <CheckCircle className="w-8 h-8 text-teal-500" />,
                      title: "Streamlined Workflows",
                      description: "Efficient approval processes and document tracking"
                    },
                  ].map((feature, index) => (
                    <div key={index} className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                      <div className="mb-4">{feature.icon}</div>
                      <h3 className="text-xl font-bold mb-2">{feature.title}</h3>
                      <p className="text-gray-600 dark:text-gray-300">{feature.description}</p>
                    </div>
                  ))}
                </div>
              </div>

              {/* Our Technology Section */}
              <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 mb-12">
                <h2 className="text-3xl font-bold mb-8 text-center">Our Technology</h2>
                <div className="flex flex-col-reverse md:flex-row gap-8 items-center">
                  <div className="md:w-2/3">
                    <p className="text-lg text-gray-600 dark:text-gray-300">
                      ProcuChain leverages blockchain technology to create an immutable ledger of all procurement
                      activities. This ensures data integrity and provides a verifiable audit trail for all transactions,
                      significantly reducing the risk of fraud and corruption in procurement processes. Our platform
                      combines the security of blockchain with a user-friendly interface that makes adoption seamless
                      for government agencies.
                    </p>
                    <div className="mt-8">
                      <Button
                        asChild
                        className="bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-700 hover:to-teal-600 text-white group"
                      >
                        <Link href="/documentation">
                          Learn More About Our Technology
                          <ArrowRight className="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" />
                        </Link>
                      </Button>
                    </div>
                  </div>
                  <div className="md:w-1/3 flex justify-center">
                    <div className="aspect-square w-48 h-48 rounded-full bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/40 dark:to-blue-900/40 flex items-center justify-center">
                      <Info className="w-20 h-20 text-teal-500" />
                    </div>
                  </div>
                </div>
              </div>

              {/* Team Section */}
              <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 mb-12">
                <h2 className="text-3xl font-bold mb-8 text-center">The Team Behind ProcuChain</h2>
                <p className="text-lg text-gray-600 dark:text-gray-300 mb-10 max-w-3xl mx-auto text-center">
                  Meet the dedicated team of innovators who developed ProcuChain as their capstone project.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                  {/* Adviser */}
                  <div className="bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 p-6 rounded-xl shadow-lg text-center">
                    <div className="w-24 h-24 mx-auto bg-white dark:bg-gray-700 rounded-full mb-4 flex items-center justify-center shadow-md">
                      <span className="text-4xl text-teal-500">A</span>
                    </div>
                    <h3 className="text-xl font-bold">Mr. Uriel M. Melendres</h3>
                    <p className="text-teal-600 dark:text-teal-400 font-medium mb-2">Project Adviser</p>
                    <p className="text-gray-600 dark:text-gray-300 text-sm">
                      Providing expert guidance and mentorship throughout the development process.
                    </p>
                  </div>

                  {/* Team Leader */}
                  <div className="bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 p-6 rounded-xl shadow-lg text-center">
                    <div className="w-24 h-24 mx-auto bg-white dark:bg-gray-700 rounded-full mb-4 flex items-center justify-center shadow-md">
                      <span className="text-4xl text-teal-500">L</span>
                    </div>
                    <h3 className="text-xl font-bold">Leodyver S. Semilla</h3>
                    <p className="text-teal-600 dark:text-teal-400 font-medium mb-2">Team Leader</p>
                    <p className="text-gray-600 dark:text-gray-300 text-sm">
                      Leading the team with vision and technical expertise in blockchain implementation.
                    </p>
                  </div>

                  {/* Team Member 1 */}
                  <div className="bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 p-6 rounded-xl shadow-lg text-center">
                    <div className="w-24 h-24 mx-auto bg-white dark:bg-gray-700 rounded-full mb-4 flex items-center justify-center shadow-md">
                      <span className="text-4xl text-teal-500">M</span>
                    </div>
                    <h3 className="text-xl font-bold">Bryle F. Maamo</h3>
                    <p className="text-teal-600 dark:text-teal-400 font-medium mb-2">Member</p>
                    <p className="text-gray-600 dark:text-gray-300 text-sm">
                      Designing and implementing the intuitive user interface and experience.
                    </p>
                  </div>

                  {/* Team Member 2 */}
                  <div className="bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 p-6 rounded-xl shadow-lg text-center">
                    <div className="w-24 h-24 mx-auto bg-white dark:bg-gray-700 rounded-full mb-4 flex items-center justify-center shadow-md">
                      <span className="text-4xl text-teal-500">M</span>
                    </div>
                    <h3 className="text-xl font-bold">Adrian P. Gupit</h3>
                    <p className="text-teal-600 dark:text-teal-400 font-medium mb-2">Member</p>
                    <p className="text-gray-600 dark:text-gray-300 text-sm">
                      Engineering the blockchain integration and server-side architecture.
                    </p>
                  </div>
                </div>
              </div>

              {/* Contact Section */}
              <div className="bg-gradient-to-br from-teal-50 to-blue-50 dark:from-teal-900/20 dark:to-blue-900/20 rounded-2xl shadow-xl p-8">
                <h2 className="text-3xl font-bold mb-6 text-center flex items-center justify-center">
                  <Mail className="w-8 h-8 text-teal-500 mr-2" />
                  Contact Us
                </h2>
                <p className="text-lg text-gray-600 dark:text-gray-300 text-center max-w-2xl mx-auto">
                  For more information about ProcuChain and how we can help optimize your procurement processes,
                  please reach out to us.
                </p>
                <div className="flex justify-center mt-8">
                  <Button
                    asChild
                    size="lg"
                    className="bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-700 hover:to-teal-600 text-white"
                  >
                    <a href="mailto:info@procuchain.com">
                      Contact Our Team
                    </a>
                  </Button>
                </div>
              </div>
            </div>
          </section>
        </main>

        <Footer />
      </div>
    </>
  );
}
