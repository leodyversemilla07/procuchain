import { useState, ChangeEvent, FormEvent, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import Header from '@/components/header';
import Footer from '@/components/footer';

// Import Shadcn UI components
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

interface Item {
    unit: string;
    description: string;
    quantity: number;
    unit_cost: number;
}

interface FormDataType {
    lgu: string;
    fund: string;
    department: string;
    pr_no: string;
    pr_date: string;
    project_name: string;
    project_location: string;
    purpose: string;
    requested_by_name: string;
    requested_by_designation: string;
    requested_by_date: string;
    budget_officer_name: string;
    budget_officer_designation: string;
    budget_availability_date: string;
    treasurer_name: string;
    treasurer_designation: string;
    cash_availability_date: string;
    approved_by_name: string;
    approved_by_designation: string;
    approved_by_date: string;
    items: Item[];
}

export default function Create() {
    const page = usePage<SharedData>();
    const { csrf_token } = page.props; // Use CSRF token from page props

    useEffect(() => {
        // Initialize fade-in content
        const fadeContent = document.querySelector('.fade-in-content');
        if (fadeContent) {
            setTimeout(() => {
                fadeContent.classList.remove('opacity-0');
                fadeContent.classList.add('opacity-100');
            }, 200);
        }
    }, []);

    const [formData, setFormData] = useState<FormDataType>({
        lgu: 'MUNICIPAL GOVERNMENT OF GLORIA',
        fund: '',
        department: '',
        pr_no: '',
        pr_date: '',
        project_name: '',
        project_location: '',
        purpose: '',
        requested_by_name: 'EDGARDO P. PELAEZ',
        requested_by_designation: 'Head Teacher III',
        requested_by_date: '',
        budget_officer_name: 'SHERALEEN C. ABUAN',
        budget_officer_designation: 'Municipal Budget Officer',
        budget_availability_date: '',
        treasurer_name: 'KAREEN M. MACABIOG',
        treasurer_designation: 'Municipal Treasurer',
        cash_availability_date: '',
        approved_by_name: 'GERMAN D. RODEGERIO',
        approved_by_designation: 'Municipal Mayor',
        approved_by_date: '',
        items: [{ unit: '', description: '', quantity: 1, unit_cost: 0 }],
    });
    const [isSubmitting, setIsSubmitting] = useState(false); // Loading state

    const handleChange = (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const { name, value } = e.target;
        setFormData({ ...formData, [name]: value });
    };

    const handleItemChange = (index: number, e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const { name, value } = e.target;
        const items = [...formData.items];
        const fieldName = name.split('[')[2].split(']')[0] as keyof Item;

        items[index] = {
            ...items[index],
            [fieldName]: fieldName === 'quantity' || fieldName === 'unit_cost' ? Number(value) : value,
        };
        setFormData({ ...formData, items });
    };

    const addItem = () => {
        setFormData({
            ...formData,
            items: [...formData.items, { unit: '', description: '', quantity: 1, unit_cost: 0 }],
        });
    };

    const removeItem = (index: number) => {
        if (window.confirm('Are you sure you want to remove this item?')) {
            const items = formData.items.filter((_, i) => i !== index);
            setFormData({ ...formData, items });
        }
    };

    const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (isSubmitting) return;

        setIsSubmitting(true);
        const form = e.target as HTMLFormElement;
        const submitData = new FormData(form);

        // Define headers with explicit type
        const headers: Record<string, string> = {};

        // Ensure csrf_token is a string before setting the header
        if (typeof csrf_token === 'string') {
            headers['X-CSRF-TOKEN'] = csrf_token;
        } else {
            console.error('csrf_token is not a string:', csrf_token);
            alert('CSRF token is invalid. Please refresh the page and try again.');
            setIsSubmitting(false);
            return;
        }

        try {
            const response = await fetch('/generate-pr-store', {
                method: 'POST',
                headers,
                body: submitData,
            });

            const responseText = await response.text();
            let jsonResponse;

            try {
                jsonResponse = JSON.parse(responseText);
            } catch {
                console.error('Error parsing response:', responseText);
                throw new Error(`Server returned invalid JSON. Status: ${response.status}`);
            }

            if (!response.ok) {
                throw new Error(jsonResponse.error || `Server error: ${response.status}`);
            }

            if (jsonResponse.success && jsonResponse.download_url) {
                window.location.href = jsonResponse.download_url;
            } else {
                throw new Error(jsonResponse.error || 'Invalid server response');
            }
        } catch (error) {
            console.error('Error generating PDF:', error);
            alert(error instanceof Error ? error.message : 'An error occurred while generating the PDF');
        } finally {
            setIsSubmitting(false);
        }
    };

    const totalAmount = formData.items.reduce((sum, item) => sum + item.quantity * item.unit_cost, 0);

    return (
        <>
            <Head title="Generate Purchase Request">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|inter:400,500,600&display=swap" rel="stylesheet" />
            </Head>

            <div className="min-h-screen bg-gradient-to-br from-white to-teal-50 text-gray-900 dark:from-gray-950 dark:to-gray-900 dark:text-white relative">
                {/* Use the Header component */}
                <Header />

                {/* Background elements */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none z-0">
                    <div className="absolute top-[20%] right-[10%] w-64 h-64 bg-teal-300/10 dark:bg-teal-600/5 rounded-full blur-3xl"></div>
                    <div className="absolute bottom-[10%] left-[5%] w-80 h-80 bg-teal-400/10 dark:bg-teal-700/5 rounded-full blur-3xl"></div>
                    <div className="absolute top-[50%] left-[30%] w-72 h-72 bg-blue-300/10 dark:bg-blue-600/5 rounded-full blur-3xl"></div>
                </div>

                {/* Hero Section - Welcome Style */}
                <div className="fade-in-content opacity-0 transition-opacity duration-1000 pt-36 pb-12 relative z-1">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                        {/* Form Section Header */}
                        <div className="text-center" id="generate-form">
                            <h2 className="text-3xl font-bold mb-4 text-gray-800 dark:text-white">
                                Create Your Purchase Request
                            </h2>
                            <p className="text-xl max-w-3xl mx-auto text-gray-600 dark:text-gray-300">
                                Fill out the form below to generate a professional purchase request document.
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
                    <Card>
                        <CardHeader>
                            <CardTitle>Request Information</CardTitle>
                            <CardDescription>
                                Enter the basic information for your purchase request
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                            <div className="space-y-2">
                                <Label htmlFor="lgu">Local Government Unit</Label>
                                <Input
                                    id="lgu"
                                    type="text"
                                    name="lgu"
                                    value={formData.lgu}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="fund">Fund</Label>
                                <Input
                                    id="fund"
                                    type="text"
                                    name="fund"
                                    value={formData.fund}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="department">Department</Label>
                                <Input
                                    id="department"
                                    type="text"
                                    name="department"
                                    value={formData.department}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pr_no">PR No.</Label>
                                <Input
                                    id="pr_no"
                                    type="text"
                                    name="pr_no"
                                    value={formData.pr_no}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pr_date">PR Date</Label>
                                <Input
                                    id="pr_date"
                                    type="date"
                                    name="pr_date"
                                    value={formData.pr_date}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="project_name">Project Name</Label>
                                <Input
                                    id="project_name"
                                    type="text"
                                    name="project_name"
                                    value={formData.project_name}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="project_location">Project Location</Label>
                                <Input
                                    id="project_location"
                                    type="text"
                                    name="project_location"
                                    value={formData.project_location}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="purpose">Purpose</Label>
                                <Input
                                    id="purpose"
                                    type="text"
                                    name="purpose"
                                    value={formData.purpose}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <div>
                                <CardTitle>Items</CardTitle>
                                <CardDescription>
                                    Add items to your purchase request
                                </CardDescription>
                            </div>
                            <Button
                                type="button"
                                onClick={addItem}
                                variant="default"
                                className="bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-700 hover:to-teal-600 hover:shadow-xl hover:shadow-teal-500/20 dark:hover:shadow-teal-700/20 transition-all duration-300"
                            >
                                <span className="flex items-center">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-5 w-5 mr-2"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fillRule="evenodd"
                                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                    Add Item
                                </span>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {formData.items.map((item, index) => (
                                <Card key={index} className="dark:bg-gray-800/60 bg-gray-50/80 border-muted">
                                    <CardContent className="p-5 space-y-4">
                                        <div className="flex items-center mb-2">
                                            <div className="text-lg font-semibold text-gray-700 dark:text-gray-300">
                                                Item #{index + 1}
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                                            <div className="space-y-2">
                                                <Label htmlFor={`item-unit-${index}`}>
                                                    Unit of Measure
                                                </Label>
                                                <Input
                                                    id={`item-unit-${index}`}
                                                    type="text"
                                                    name={`items[${index}][unit]`}
                                                    value={item.unit}
                                                    onChange={(e) => handleItemChange(index, e)}
                                                    placeholder="Unit"
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2 lg:col-span-2">
                                                <Label htmlFor={`item-description-${index}`}>
                                                    Item Description
                                                </Label>
                                                <Textarea
                                                    id={`item-description-${index}`}
                                                    name={`items[${index}][description]`}
                                                    value={item.description}
                                                    onChange={(e: ChangeEvent<HTMLTextAreaElement>) => handleItemChange(index, e)}
                                                    placeholder="Description"
                                                    rows={2}
                                                    required
                                                />
                                            </div>
                                            <div className="md:col-span-1">
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor={`item-quantity-${index}`}>
                                                            Quantity
                                                        </Label>
                                                        <Input
                                                            id={`item-quantity-${index}`}
                                                            type="number"
                                                            name={`items[${index}][quantity]`}
                                                            value={item.quantity}
                                                            onChange={(e) => handleItemChange(index, e)}
                                                            placeholder="Quantity"
                                                            min="1"
                                                            required
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor={`item-unit-cost-${index}`}>
                                                            Unit Cost
                                                        </Label>
                                                        <Input
                                                            id={`item-unit-cost-${index}`}
                                                            type="number"
                                                            name={`items[${index}][unit_cost]`}
                                                            value={item.unit_cost}
                                                            onChange={(e) => handleItemChange(index, e)}
                                                            placeholder="Unit Cost"
                                                            min="0"
                                                            step="0.01"
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <Separator className="my-2" />
                                        <div className="flex justify-between items-center">
                                            <div className="font-medium text-gray-700 dark:text-gray-300">
                                                {item.quantity && item.unit_cost ? (
                                                    <span>
                                                        Total: ₱{(item.quantity * item.unit_cost).toLocaleString('en-US', {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2,
                                                        })}
                                                    </span>
                                                ) : (
                                                    <span>Total: ₱0.00</span>
                                                )}
                                            </div>
                                            <Button
                                                type="button"
                                                onClick={() => removeItem(index)}
                                                variant="destructive"
                                                size="sm"
                                            >
                                                <span className="flex items-center">
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        className="h-4 w-4 mr-1.5"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                    >
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                    Remove
                                                </span>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </CardContent>
                        <CardFooter>
                            {formData.items.length > 0 && (
                                <div className="w-full p-4 rounded-lg bg-muted/50 text-right font-semibold">
                                    Total Amount: ₱{totalAmount.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })}
                                </div>
                            )}
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Signatories</CardTitle>
                            <CardDescription>
                                Provide signatories information for this purchase request
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-8">
                            {/* Requested By Section */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="requested_by_name">Requested by Name</Label>
                                    <Input
                                        id="requested_by_name"
                                        type="text"
                                        name="requested_by_name"
                                        value={formData.requested_by_name}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="requested_by_designation">Designation</Label>
                                    <Input
                                        id="requested_by_designation"
                                        type="text"
                                        name="requested_by_designation"
                                        value={formData.requested_by_designation}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                            </div>

                            <Separator />

                            {/* Budget Officer Section */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="budget_officer_name">Budget Officer</Label>
                                    <Input
                                        id="budget_officer_name"
                                        type="text"
                                        name="budget_officer_name"
                                        value={formData.budget_officer_name}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="budget_officer_designation">Designation</Label>
                                    <Input
                                        id="budget_officer_designation"
                                        type="text"
                                        name="budget_officer_designation"
                                        value={formData.budget_officer_designation}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                            </div>

                            <Separator />

                            {/* Treasurer Section */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="treasurer_name">Treasurer</Label>
                                    <Input
                                        id="treasurer_name"
                                        type="text"
                                        name="treasurer_name"
                                        value={formData.treasurer_name}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="treasurer_designation">Designation</Label>
                                    <Input
                                        id="treasurer_designation"
                                        type="text"
                                        name="treasurer_designation"
                                        value={formData.treasurer_designation}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                            </div>

                            <Separator />

                            {/* Approved By Section */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="approved_by_name">Approved by</Label>
                                    <Input
                                        id="approved_by_name"
                                        type="text"
                                        name="approved_by_name"
                                        value={formData.approved_by_name}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                                <div className="space-y-2 w-full">
                                    <Label htmlFor="approved_by_designation">Designation</Label>
                                    <Input
                                        id="approved_by_designation"
                                        type="text"
                                        name="approved_by_designation"
                                        value={formData.approved_by_designation}
                                        onChange={handleChange}
                                        required
                                        className="w-full"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={isSubmitting}
                            className={cn(
                                "flex items-center bg-gradient-to-r from-teal-600 to-teal-500 hover:from-teal-700 hover:to-teal-600 hover:shadow-xl hover:shadow-teal-500/20 dark:hover:shadow-teal-700/20 transition-all duration-300 hover:translate-y-[-2px]",
                                isSubmitting && "opacity-50 cursor-not-allowed"
                            )}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="h-5 w-5 mr-2"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V8z"
                                    clipRule="evenodd"
                                />
                            </svg>
                            {isSubmitting ? 'Generating...' : 'Generate PDF'}
                        </Button>
                    </div>
                </form>

                {/* Use the Footer component */}
                <Footer />

                {/* Add global styles */}
                <style>{`
                    body {
                        font-family: 'Inter', sans-serif;
                    }
                    h1, h2, h3, h4, h5, h6 {
                        font-family: 'Outfit', sans-serif;
                    }
                    .fade-in-content.is-visible {
                        opacity: 1;
                    }
                    .animate-pulse-slow {
                        animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
                    }
                    @keyframes pulse {
                        0%, 100% {
                            opacity: 0.5;
                        }
                        50% {
                            opacity: 0.8;
                        }
                    }
                `}</style>
            </div>
        </>
    );
}