import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, FileCheck, FileText, Plus, RotateCcw, Save, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

// Wayfinder imports
import { resetToDefaults, update } from '@/actions/App/Http/Controllers/Admin/StageDocumentConfigController';

interface Document {
    value: string;
    display_name: string;
    description: string;
}

interface Mode {
    value: string;
    display_name: string;
}

interface Stage {
    value: string;
    display_name: string;
    description: string;
    phase: string;
    phase_display_name: string;
}

interface PageProps {
    mode: Mode;
    stage: Stage;
    currentRequiredDocuments: string[];
    currentOptionalDocuments: string[];
    allDocuments: Document[];
}

const breadcrumbs = (modeName: string, stageName: string) => [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Stage Documents', href: '/admin/stage-documents' },
    { title: `${stageName} (${modeName})`, href: '#' },
];

export default function StageDocumentConfigEdit({ mode, stage, currentRequiredDocuments, currentOptionalDocuments, allDocuments }: PageProps) {
    const [requiredDocs, setRequiredDocs] = useState<string[]>(currentRequiredDocuments);
    const [optionalDocs, setOptionalDocs] = useState<string[]>(currentOptionalDocuments);
    const [searchQuery, setSearchQuery] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [customDocName, setCustomDocName] = useState('');

    const isModified =
        JSON.stringify(requiredDocs.sort()) !== JSON.stringify([...currentRequiredDocuments].sort()) ||
        JSON.stringify(optionalDocs.sort()) !== JSON.stringify([...currentOptionalDocuments].sort());

    const filteredDocuments = useMemo(() => {
        if (!searchQuery) return allDocuments;
        const query = searchQuery.toLowerCase();
        return allDocuments.filter(
            (doc) =>
                doc.display_name.toLowerCase().includes(query) ||
                doc.description.toLowerCase().includes(query) ||
                doc.value.toLowerCase().includes(query),
        );
    }, [allDocuments, searchQuery]);

    const availableDocuments = useMemo(() => {
        return filteredDocuments.filter((doc) => !requiredDocs.includes(doc.value) && !optionalDocs.includes(doc.value));
    }, [filteredDocuments, requiredDocs, optionalDocs]);

    // Convert custom name to a value (snake_case)
    const toDocValue = (name: string) => {
        return name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '');
    };

    const handleAddCustomRequired = () => {
        if (!customDocName.trim()) {
            toast.error('Please enter a document name');
            return;
        }
        const docValue = toDocValue(customDocName);
        if (requiredDocs.includes(docValue) || optionalDocs.includes(docValue)) {
            toast.error('This document is already added');
            return;
        }
        setRequiredDocs([...requiredDocs, docValue]);
        toast.success(`Added "${customDocName}" to required documents`);
        setCustomDocName('');
    };

    const handleAddCustomOptional = () => {
        if (!customDocName.trim()) {
            toast.error('Please enter a document name');
            return;
        }
        const docValue = toDocValue(customDocName);
        if (requiredDocs.includes(docValue) || optionalDocs.includes(docValue)) {
            toast.error('This document is already added');
            return;
        }
        setOptionalDocs([...optionalDocs, docValue]);
        toast.success(`Added "${customDocName}" to optional documents`);
        setCustomDocName('');
    };

    const handleAddToRequired = (docValue: string) => {
        setRequiredDocs([...requiredDocs, docValue]);
    };

    const handleAddToOptional = (docValue: string) => {
        setOptionalDocs([...optionalDocs, docValue]);
    };

    const handleRemoveFromRequired = (docValue: string) => {
        setRequiredDocs(requiredDocs.filter((d) => d !== docValue));
    };

    const handleRemoveFromOptional = (docValue: string) => {
        setOptionalDocs(optionalDocs.filter((d) => d !== docValue));
    };

    const handleMoveToOptional = (docValue: string) => {
        setRequiredDocs(requiredDocs.filter((d) => d !== docValue));
        setOptionalDocs([...optionalDocs, docValue]);
    };

    const handleMoveToRequired = (docValue: string) => {
        setOptionalDocs(optionalDocs.filter((d) => d !== docValue));
        setRequiredDocs([...requiredDocs, docValue]);
    };

    const handleSave = () => {
        setIsSubmitting(true);
        router.put(
            update({ mode: mode.value, stage: stage.value }).url,
            {
                required_documents: requiredDocs,
                optional_documents: optionalDocs,
            },
            {
                onSuccess: () => {
                    toast.success('Document configuration saved successfully');
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    console.error(errors);
                    toast.error('Failed to save document configuration');
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleReset = () => {
        router.post(
            resetToDefaults({ mode: mode.value, stage: stage.value }).url,
            {},
            {
                onSuccess: () => {
                    toast.success('Document configuration reset to defaults');
                },
                onError: () => {
                    toast.error('Failed to reset document configuration');
                },
            },
        );
    };

    const getDocumentByValue = (value: string) => {
        return allDocuments.find((d) => d.value === value);
    };

    const DocumentItem = ({ doc, onRemove, onMove, moveLabel }: { doc: Document; onRemove: () => void; onMove?: () => void; moveLabel?: string }) => (
        <div className="bg-card flex items-center gap-3 rounded-lg border p-3">
            <FileText className="text-muted-foreground h-4 w-4 shrink-0" />
            <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium">{doc.display_name}</div>
                <div className="text-muted-foreground truncate text-xs">{doc.description}</div>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                {onMove && (
                    <Button variant="ghost" size="sm" onClick={onMove} className="h-7 text-xs">
                        {moveLabel}
                    </Button>
                )}
                <Button variant="ghost" size="sm" onClick={onRemove} className="text-destructive h-7 text-xs">
                    Remove
                </Button>
            </div>
        </div>
    );

    const AvailableDocItem = ({ doc }: { doc: Document }) => (
        <div className="bg-muted/30 hover:bg-muted/50 flex items-start gap-3 rounded-lg border p-3 transition-colors">
            <FileText className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
            <div className="min-w-0 flex-1 overflow-hidden">
                <div className="truncate text-sm font-medium">{doc.display_name}</div>
                <div className="text-muted-foreground truncate text-xs">{doc.description}</div>
                <div className="mt-2 flex items-center gap-1">
                    <Button variant="outline" size="sm" onClick={() => handleAddToRequired(doc.value)} className="h-7 text-xs">
                        + Required
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => handleAddToOptional(doc.value)} className="h-7 text-xs">
                        + Optional
                    </Button>
                </div>
            </div>
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs(mode.display_name, stage.display_name)}>
            <Head title={`Configure ${stage.display_name}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <HeroCard
                    icon={FileText}
                    title={stage.display_name}
                    description={`${mode.display_name} • ${stage.phase_display_name}`}
                    actions={
                        <>
                            <Button variant="outline" size="sm" onClick={handleReset}>
                                <RotateCcw className="mr-2 h-4 w-4" />
                                Reset to Defaults
                            </Button>
                            <Button onClick={handleSave} disabled={!isModified || isSubmitting} size="sm">
                                <Save className="mr-2 h-4 w-4" />
                                {isSubmitting ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </>
                    }
                />

                {/* Warning */}
                {isModified && (
                    <Card className="border-yellow-500/50 bg-yellow-500/5">
                        <CardContent className="flex items-center gap-3 p-4">
                            <AlertTriangle className="h-5 w-5 text-yellow-600" />
                            <p className="text-sm">You have unsaved changes. Click "Save Changes" to apply them.</p>
                        </CardContent>
                    </Card>
                )}

                {/* Summary */}
                <div className="flex items-center gap-6 text-sm">
                    <div className="flex items-center gap-2">
                        <FileCheck className="text-primary h-4 w-4" />
                        <span className="text-muted-foreground">Required:</span>
                        <Badge variant="default">{requiredDocs.length}</Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        <FileText className="text-muted-foreground h-4 w-4" />
                        <span className="text-muted-foreground">Optional:</span>
                        <Badge variant="secondary">{optionalDocs.length}</Badge>
                    </div>
                </div>

                {/* Add Custom Document Section */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                            <Plus className="text-muted-foreground hidden h-5 w-5 shrink-0 sm:block" />
                            <div className="w-full flex-1 sm:w-auto">
                                <Label className="text-sm font-medium">Add Document by Name</Label>
                                <p className="text-muted-foreground text-xs">Type the name of the document you want to add</p>
                            </div>
                            <div className="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:flex-row sm:items-center">
                                <Input
                                    placeholder="Enter document name..."
                                    value={customDocName}
                                    onChange={(e) => setCustomDocName(e.target.value)}
                                    className="w-full sm:w-[250px]"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleAddCustomRequired();
                                        }
                                    }}
                                />
                                <div className="flex gap-2">
                                    <Button variant="default" size="sm" onClick={handleAddCustomRequired} disabled={!customDocName.trim()}>
                                        + Required
                                    </Button>
                                    <Button variant="secondary" size="sm" onClick={handleAddCustomOptional} disabled={!customDocName.trim()}>
                                        + Optional
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Separator />

                {/* Main Content */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Left: Selected Documents */}
                    <div className="space-y-4">
                        <Tabs defaultValue="required">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="required">Required ({requiredDocs.length})</TabsTrigger>
                                <TabsTrigger value="optional">Optional ({optionalDocs.length})</TabsTrigger>
                            </TabsList>

                            <TabsContent value="required" className="mt-4">
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base">Required Documents</CardTitle>
                                        <CardDescription>These documents must be uploaded to complete the stage</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ScrollArea className="h-[400px]">
                                            <div className="space-y-2 pr-4">
                                                {requiredDocs.length === 0 ? (
                                                    <div className="text-muted-foreground py-8 text-center text-sm">
                                                        No required documents selected
                                                    </div>
                                                ) : (
                                                    requiredDocs.map((docValue) => {
                                                        const doc = getDocumentByValue(docValue);
                                                        if (!doc) return null;
                                                        return (
                                                            <DocumentItem
                                                                key={docValue}
                                                                doc={doc}
                                                                onRemove={() => handleRemoveFromRequired(docValue)}
                                                                onMove={() => handleMoveToOptional(docValue)}
                                                                moveLabel="→ Optional"
                                                            />
                                                        );
                                                    })
                                                )}
                                            </div>
                                        </ScrollArea>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="optional" className="mt-4">
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base">Optional Documents</CardTitle>
                                        <CardDescription>These documents can be uploaded but are not required</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ScrollArea className="h-[400px]">
                                            <div className="space-y-2 pr-4">
                                                {optionalDocs.length === 0 ? (
                                                    <div className="text-muted-foreground py-8 text-center text-sm">
                                                        No optional documents selected
                                                    </div>
                                                ) : (
                                                    optionalDocs.map((docValue) => {
                                                        const doc = getDocumentByValue(docValue);
                                                        if (!doc) return null;
                                                        return (
                                                            <DocumentItem
                                                                key={docValue}
                                                                doc={doc}
                                                                onRemove={() => handleRemoveFromOptional(docValue)}
                                                                onMove={() => handleMoveToRequired(docValue)}
                                                                moveLabel="→ Required"
                                                            />
                                                        );
                                                    })
                                                )}
                                            </div>
                                        </ScrollArea>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>

                    {/* Right: Available Documents */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Available Documents</CardTitle>
                            <CardDescription>Click to add documents to required or optional list</CardDescription>
                            <div className="relative mt-2">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    placeholder="Search documents..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="h-[450px] space-y-2 overflow-y-auto">
                                {availableDocuments.length === 0 ? (
                                    <div className="text-muted-foreground py-8 text-center text-sm">
                                        {searchQuery ? 'No documents match your search' : 'All documents have been added'}
                                    </div>
                                ) : (
                                    availableDocuments.map((doc) => <AvailableDocItem key={doc.value} doc={doc} />)
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
