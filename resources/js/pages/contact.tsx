import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import Footer from '@/components/footer';
import {
    Mail,
    MessageSquare,
    MapPin,
    Clock,
    Send,
    Building,
    GraduationCap,
    Github,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";

export default function Contact() {
    const [formState, setFormState] = useState({
        name: '',
        email: '',
        subject: '',
        message: ''
    });

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const { name, value } = e.target;
        setFormState(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        // Simulate form submission - in a real app, this would be an API call
        await new Promise(resolve => setTimeout(resolve, 1500));

        // Reset form and show success message
        setFormState({
            name: '',
            email: '',
            subject: '',
            message: ''
        });
        setIsSubmitting(false);
        setSubmitted(true);

        // Hide success message after 5 seconds
        setTimeout(() => setSubmitted(false), 5000);
    };

    return (
        <>
            <Head title="Contact Us">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
                <meta name="description" content="Contact the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system." />
            </Head>
            <div className="min-h-screen flex flex-col bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
                <Header />

                <main className="flex-grow pt-24 pb-16">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Hero Section */}
                        <div className="mb-16 text-center">
                            <div className="inline-block p-2 bg-teal-100/60 dark:bg-teal-900/30 rounded-lg text-teal-700 dark:text-teal-300 mb-4">
                                <MessageSquare className="w-6 h-6" />
                            </div>
                            <h1 className="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                                <span className="bg-gradient-to-r from-teal-600 to-teal-400 bg-clip-text text-transparent">
                                    Get In Touch
                                </span>
                            </h1>
                            <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-3xl mx-auto">
                                Have questions about ProcuChain? Want to learn more about our blockchain-powered
                                procurement system? We'd love to hear from you.
                            </p>
                            <div className="flex flex-wrap justify-center gap-4">
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-teal-50 dark:bg-teal-900/30 border-teal-200 dark:border-teal-800">
                                    <Mail className="w-3.5 h-3.5 mr-1" />
                                    Project Inquiries
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800">
                                    <Users className="w-3.5 h-3.5 mr-1" />
                                    Academic Collaboration
                                </Badge>
                                <Badge variant="outline" className="px-3 py-1 text-sm bg-purple-50 dark:bg-purple-900/30 border-purple-200 dark:border-purple-800">
                                    <Github className="w-3.5 h-3.5 mr-1" />
                                    Technical Support
                                </Badge>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-16">
                            {/* Contact Information Panel */}
                            <div className="lg:col-span-1">
                                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                    <h2 className="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Contact Information</h2>

                                    <div className="space-y-6">
                                        <div className="flex items-start">
                                            <div className="flex-shrink-0 bg-teal-100 dark:bg-teal-900/30 p-3 rounded-full text-teal-700 dark:text-teal-300 mr-4">
                                                <Mail className="w-5 h-5" />
                                            </div>
                                            <div>
                                                <h3 className="text-sm font-medium text-gray-900 dark:text-white">Email</h3>
                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                    <a href="mailto:procuchain@minsu.edu.ph" className="hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                                                        semilla.leodyver@minsu.edu.ph
                                                    </a>
                                                </p>
                                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">For general inquiries</p>
                                            </div>
                                        </div>

                                        <div className="flex items-start">
                                            <div className="flex-shrink-0 bg-blue-100 dark:bg-blue-900/30 p-3 rounded-full text-blue-700 dark:text-blue-300 mr-4">
                                                <Building className="w-5 h-5" />
                                            </div>
                                            <div>
                                                <h3 className="text-sm font-medium text-gray-900 dark:text-white">Institution</h3>
                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                    Mindoro State University - Bongabong Campus<br />
                                                    College of Computer Studies<br />
                                                    Information Technology Department<br />
                                                    Bongabong, Oriental Mindoro
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-start">
                                            <div className="flex-shrink-0 bg-amber-100 dark:bg-amber-900/30 p-3 rounded-full text-amber-700 dark:text-amber-300 mr-4">
                                                <Clock className="w-5 h-5" />
                                            </div>
                                            <div>
                                                <h3 className="text-sm font-medium text-gray-900 dark:text-white">Office Hours</h3>
                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                    Monday - Friday<br />
                                                    8:00 AM - 5:00 PM
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                                        <h3 className="text-sm font-medium text-gray-900 dark:text-white mb-4">Connect With Us</h3>
                                        <div className="flex space-x-4">
                                            <a
                                                href="https://github.com/leodyversemilla07/procuchain"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="bg-gray-100 dark:bg-gray-700 hover:bg-teal-100 dark:hover:bg-teal-900/30 p-3 rounded-full transition-colors"
                                            >
                                                <Github className="w-5 h-5 text-gray-700 dark:text-gray-300" />
                                            </a>
                                            <a
                                                href="https://minsu.edu.ph"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="bg-gray-100 dark:bg-gray-700 hover:bg-teal-100 dark:hover:bg-teal-900/30 p-3 rounded-full transition-colors"
                                            >
                                                <GraduationCap className="w-5 h-5 text-gray-700 dark:text-gray-300" />
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Contact Form */}
                            <div className="lg:col-span-2">
                                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                    <h2 className="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Send Us a Message</h2>

                                    {submitted ? (
                                        <div className="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                                            <div className="flex">
                                                <div className="flex-shrink-0">
                                                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div className="ml-3">
                                                    <h3 className="text-sm font-medium text-green-800 dark:text-green-200">
                                                        Message Sent Successfully!
                                                    </h3>
                                                    <p className="mt-2 text-sm text-green-600 dark:text-green-300">
                                                        Thank you for contacting us. We've received your message and will respond as soon as possible.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <form onSubmit={handleSubmit} className="space-y-6">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <Label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                        Your Name
                                                    </Label>
                                                    <Input
                                                        id="name"
                                                        name="name"
                                                        type="text"
                                                        required
                                                        value={formState.name}
                                                        onChange={handleChange}
                                                        placeholder="John Doe"
                                                        className="w-full"
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                        Email Address
                                                    </Label>
                                                    <Input
                                                        id="email"
                                                        name="email"
                                                        type="email"
                                                        required
                                                        value={formState.email}
                                                        onChange={handleChange}
                                                        placeholder="john@example.com"
                                                        className="w-full"
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <Label htmlFor="subject" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Subject
                                                </Label>
                                                <Input
                                                    id="subject"
                                                    name="subject"
                                                    type="text"
                                                    required
                                                    value={formState.subject}
                                                    onChange={handleChange}
                                                    placeholder="How can we help you?"
                                                    className="w-full"
                                                />
                                            </div>

                                            <div>
                                                <Label htmlFor="message" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Message
                                                </Label>
                                                <Textarea
                                                    id="message"
                                                    name="message"
                                                    required
                                                    value={formState.message}
                                                    onChange={handleChange}
                                                    placeholder="Your message here..."
                                                    className="w-full min-h-[150px]"
                                                />
                                            </div>

                                            <div className="flex justify-end">
                                                <Button
                                                    type="submit"
                                                    disabled={isSubmitting}
                                                    className="bg-teal-600 hover:bg-teal-700 text-white flex items-center"
                                                >
                                                    {isSubmitting ? (
                                                        <>
                                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                            Sending...
                                                        </>
                                                    ) : (
                                                        <>
                                                            Send Message
                                                            <Send className="ml-2 w-4 h-4" />
                                                        </>
                                                    )}
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* FAQ Section */}
                        <div className="mb-16">
                            <h2 className="text-2xl font-bold mb-8 text-center">Frequently Asked Questions</h2>

                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                <Accordion type="single" collapsible className="w-full">
                                    <AccordionItem value="item-1">
                                        <AccordionTrigger className="text-left text-gray-900 dark:text-white">
                                            What is ProcuChain and how does it use blockchain?
                                        </AccordionTrigger>
                                        <AccordionContent className="text-gray-600 dark:text-gray-300">
                                            ProcuChain is a blockchain-powered procurement management system designed to enhance transparency and
                                            security in government procurement processes. It uses blockchain technology to create immutable
                                            records of procurement documents and activities, preventing tampering and establishing a
                                            verifiable audit trail.
                                        </AccordionContent>
                                    </AccordionItem>

                                    <AccordionItem value="item-2">
                                        <AccordionTrigger className="text-left text-gray-900 dark:text-white">
                                            Is ProcuChain an open-source project?
                                        </AccordionTrigger>
                                        <AccordionContent className="text-gray-600 dark:text-gray-300">
                                            Yes, ProcuChain is an open-source project developed as a capstone project at Mindoro State University.
                                            The source code is available on GitHub, and we welcome contributions from the community.
                                        </AccordionContent>
                                    </AccordionItem>

                                    <AccordionItem value="item-3">
                                        <AccordionTrigger className="text-left text-gray-900 dark:text-white">
                                            How can I contribute to the ProcuChain project?
                                        </AccordionTrigger>
                                        <AccordionContent className="text-gray-600 dark:text-gray-300">
                                            We welcome contributions from developers, researchers, and procurement specialists. You can contribute by:
                                            <ul className="list-disc pl-5 mt-2 space-y-1">
                                                <li>Submitting pull requests on GitHub</li>
                                                <li>Reporting bugs and suggesting features</li>
                                                <li>Providing domain expertise in procurement processes</li>
                                                <li>Helping with documentation and testing</li>
                                            </ul>
                                        </AccordionContent>
                                    </AccordionItem>

                                    <AccordionItem value="item-4">
                                        <AccordionTrigger className="text-left text-gray-900 dark:text-white">
                                            Can ProcuChain be implemented in organizations outside government?
                                        </AccordionTrigger>
                                        <AccordionContent className="text-gray-600 dark:text-gray-300">
                                            Absolutely. While ProcuChain was initially designed with government procurement processes in mind,
                                            the system is adaptable to any organization that requires transparent, secure procurement management.
                                            The blockchain foundation makes it suitable for any context where accountability and auditability are important.
                                        </AccordionContent>
                                    </AccordionItem>

                                    <AccordionItem value="item-5">
                                        <AccordionTrigger className="text-left text-gray-900 dark:text-white">
                                            How can I learn more about the technical aspects of ProcuChain?
                                        </AccordionTrigger>
                                        <AccordionContent className="text-gray-600 dark:text-gray-300">
                                            You can explore our documentation section for technical details, architecture diagrams, and implementation guides.
                                            For more specific information, you can also reach out to our team directly through this contact form.
                                        </AccordionContent>
                                    </AccordionItem>
                                </Accordion>
                            </div>
                        </div>

                        {/* Map/Location Section */}
                        <div className="mb-16">
                            <h2 className="text-2xl font-bold mb-8 text-center">Our Location</h2>

                            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                                <div className="h-[600px] w-full bg-gray-200 dark:bg-gray-700 relative">
                                    {/* Google Maps embed */}
                                    <iframe
                                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2751.4238779925745!2d121.47536697941344!3d12.771954782626889!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bb505bed5021ff%3A0xbe706e109962463c!2sMindoro%20State%20University%2C%20Bongabong%20Campus!5e0!3m2!1sen!2sph!4v1746281396699!5m2!1sen!2sph"
                                        className="absolute inset-0 w-full h-full border-0"
                                        style={{ minHeight: "600px" }}
                                        allowFullScreen
                                        loading="lazy"
                                        referrerPolicy="no-referrer-when-downgrade"
                                    ></iframe>
                                    <div className="absolute bottom-0 left-0 right-0 bg-white dark:bg-gray-800 bg-opacity-90 dark:bg-opacity-90 p-4">
                                        <p className="font-medium text-gray-900 dark:text-white">Mindoro State University - Bongabong</p>
                                        <p className="text-sm text-gray-600 dark:text-gray-300">Labasan, Bongabong, Oriental Mindoro, Philippines</p>
                                    </div>
                                </div>
                                <div className="p-4 flex justify-center">
                                    <Button asChild variant="outline" className="border-teal-600 text-teal-600 hover:bg-teal-50 dark:border-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20">
                                        <a href="https://maps.app.goo.gl/N5ZGtdHE7W6NNJ7J8" target="_blank" rel="noopener noreferrer">
                                            Get Directions
                                            <MapPin className="ml-2 w-4 h-4" />
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                <Footer />
            </div>
        </>
    );
}