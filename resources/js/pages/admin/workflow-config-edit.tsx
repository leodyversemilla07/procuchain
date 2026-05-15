import { HeroCard } from '@/components/hero-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { closestCenter, DndContext, DragEndEvent, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, GitBranch, GripVertical, RotateCcw, Save } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

// Wayfinder imports
import { resetToDefaults, update } from '@/actions/App/Http/Controllers/Admin/ProcurementWorkflowConfigController';

interface Stage {
    value: string;
    display_name: string;
    description: string;
    phase: string;
    phase_display_name: string;
}

interface Mode {
    value: string;
    display_name: string;
    description: string;
    irr_section: string;
}

interface PageProps {
    mode: Mode;
    currentStages: string[];
    currentOptionalStages: string[];
    defaultStages: string[];
    defaultOptionalStages: string[];
    allStages: Stage[];
}

const breadcrumbs = (modeName: string) => [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Workflow Configuration', href: '/admin/workflow-config' },
    { title: modeName, href: '#' },
];

// Sortable Stage Item Component
function SortableStageItem({
    stage,
    isSelected,
    isOptional,
    isDefault,
    onToggleStage,
    onToggleOptional,
}: {
    stage: Stage;
    isSelected: boolean;
    isOptional: boolean;
    isDefault: boolean;
    onToggleStage: (value: string) => void;
    onToggleOptional: (value: string) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: stage.value,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        zIndex: isDragging ? 1000 : undefined,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                isSelected ? 'border-primary/50 bg-primary/5' : 'border-border bg-muted/30 opacity-60'
            } ${isDragging ? 'shadow-lg' : ''}`}
        >
            <button type="button" className="cursor-grab touch-none active:cursor-grabbing" {...attributes} {...listeners}>
                <GripVertical className="text-muted-foreground h-4 w-4" />
            </button>

            <Checkbox id={`stage-${stage.value}`} checked={isSelected} onCheckedChange={() => onToggleStage(stage.value)} />

            <div className="flex-1 space-y-0.5">
                <Label htmlFor={`stage-${stage.value}`} className="cursor-pointer font-medium">
                    {stage.display_name}
                </Label>
                <p className="text-muted-foreground text-xs">{stage.description}</p>
            </div>

            <div className="flex items-center gap-2">
                {!isDefault && isSelected && (
                    <Badge variant="outline" className="text-xs text-yellow-600">
                        Added
                    </Badge>
                )}
                {isDefault && !isSelected && (
                    <Badge variant="outline" className="text-xs text-red-600">
                        Removed
                    </Badge>
                )}
                {isSelected && (
                    <div className="flex items-center gap-1.5">
                        <Checkbox id={`optional-${stage.value}`} checked={isOptional} onCheckedChange={() => onToggleOptional(stage.value)} />
                        <Label htmlFor={`optional-${stage.value}`} className="text-muted-foreground cursor-pointer text-xs">
                            Optional
                        </Label>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function WorkflowConfigEdit({
    mode,
    currentStages,
    currentOptionalStages,
    defaultStages,
    defaultOptionalStages,
    allStages,
}: PageProps) {
    const [selectedStages, setSelectedStages] = useState<string[]>(currentStages);
    const [optionalStages, setOptionalStages] = useState<string[]>(currentOptionalStages);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Maintain order of all stages (for drag-and-drop)
    const [stageOrder, setStageOrder] = useState<string[]>(() => {
        // Start with current stages in order, then add remaining stages
        const currentOrder = [...currentStages];
        allStages.forEach((stage) => {
            if (!currentOrder.includes(stage.value)) {
                currentOrder.push(stage.value);
            }
        });
        return currentOrder;
    });

    const { processing } = useForm();

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8, // Require 8px movement before starting drag
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const isModified =
        JSON.stringify(selectedStages) !== JSON.stringify(currentStages) || JSON.stringify(optionalStages) !== JSON.stringify(currentOptionalStages);

    const isDifferentFromDefault =
        JSON.stringify(selectedStages) !== JSON.stringify(defaultStages) || JSON.stringify(optionalStages) !== JSON.stringify(defaultOptionalStages);

    const handleToggleStage = (stageValue: string) => {
        if (selectedStages.includes(stageValue)) {
            setSelectedStages(selectedStages.filter((s) => s !== stageValue));
            setOptionalStages(optionalStages.filter((s) => s !== stageValue));
        } else {
            // Add to selected stages in the current order position
            const newSelected = stageOrder.filter((s) => selectedStages.includes(s) || s === stageValue);
            setSelectedStages(newSelected);
        }
    };

    const handleToggleOptional = (stageValue: string) => {
        if (optionalStages.includes(stageValue)) {
            setOptionalStages(optionalStages.filter((s) => s !== stageValue));
        } else {
            setOptionalStages([...optionalStages, stageValue]);
        }
    };

    const handleDragEnd = (event: DragEndEvent, phase: string) => {
        const { active, over } = event;

        if (over && active.id !== over.id) {
            const phaseStages = stageOrder.filter((s) => {
                const stage = allStages.find((st) => st.value === s);
                return stage?.phase === phase;
            });

            const oldIndex = phaseStages.indexOf(active.id as string);
            const newIndex = phaseStages.indexOf(over.id as string);
            const newPhaseOrder = arrayMove(phaseStages, oldIndex, newIndex);

            // Update the full order maintaining other phases
            const newOrder = stageOrder.filter((s) => {
                const stage = allStages.find((st) => st.value === s);
                return stage?.phase !== phase;
            });

            // Insert phase stages back in their relative positions
            let insertIndex = 0;
            stageOrder.forEach((s, i) => {
                const stage = allStages.find((st) => st.value === s);
                if (stage?.phase === phase && insertIndex === 0) {
                    insertIndex = newOrder.findIndex((_, idx) => idx >= i) || newOrder.length;
                }
            });

            // Rebuild the order with the new phase order
            const finalOrder: string[] = [];
            let phaseIdx = 0;
            stageOrder.forEach((s) => {
                const stage = allStages.find((st) => st.value === s);
                if (stage?.phase === phase) {
                    finalOrder.push(newPhaseOrder[phaseIdx++]);
                } else {
                    finalOrder.push(s);
                }
            });

            setStageOrder(finalOrder);

            // Update selected stages order
            const newSelectedStages = finalOrder.filter((s) => selectedStages.includes(s));
            setSelectedStages(newSelectedStages);
        }
    };

    const handleSave = () => {
        if (selectedStages.length === 0) {
            toast.error('At least one stage is required');
            return;
        }

        setIsSubmitting(true);
        router.put(
            update(mode.value).url,
            {
                stages: selectedStages,
                optional_stages: optionalStages,
            },
            {
                onSuccess: () => {
                    toast.success('Workflow configuration saved successfully');
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    console.error(errors);
                    toast.error('Failed to save workflow configuration');
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleResetLocal = () => {
        setSelectedStages(defaultStages);
        setOptionalStages(defaultOptionalStages);
        toast.info('Reset to default values (not saved yet)');
    };

    const handleResetAndSave = () => {
        router.post(
            resetToDefaults(mode.value).url,
            {},
            {
                onSuccess: () => {
                    toast.success('Workflow configuration reset to defaults and saved');
                },
                onError: () => {
                    toast.error('Failed to reset workflow configuration');
                },
            },
        );
    };

    // Get stages for each phase in the current order
    const getOrderedPhaseStages = (phase: string) => {
        return stageOrder.map((value) => allStages.find((s) => s.value === value)).filter((s): s is Stage => s !== undefined && s.phase === phase);
    };

    const preProcurementStages = getOrderedPhaseStages('pre_procurement');
    const procurementStages = getOrderedPhaseStages('procurement');
    const postProcurementStages = getOrderedPhaseStages('post_procurement');

    const PhaseCard = ({ title, description, stages, phase }: { title: string; description: string; stages: Stage[]; phase: string }) => (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-lg">{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={(e) => handleDragEnd(e, phase)}>
                    <SortableContext items={stages.map((s) => s.value)} strategy={verticalListSortingStrategy}>
                        {stages.map((stage) => (
                            <SortableStageItem
                                key={stage.value}
                                stage={stage}
                                isSelected={selectedStages.includes(stage.value)}
                                isOptional={optionalStages.includes(stage.value)}
                                isDefault={defaultStages.includes(stage.value)}
                                onToggleStage={handleToggleStage}
                                onToggleOptional={handleToggleOptional}
                            />
                        ))}
                    </SortableContext>
                </DndContext>
                {stages.length === 0 && <p className="text-muted-foreground py-4 text-center text-sm">No stages in this phase</p>}
            </CardContent>
        </Card>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs(mode.display_name)}>
            <Head title={`Configure ${mode.display_name}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <HeroCard
                    icon={GitBranch}
                    title={`Configure ${mode.display_name}`}
                    description={`${mode.irr_section} • ${mode.description}`}
                    actions={
                        <>
                            {isDifferentFromDefault && (
                                <>
                                    <Button variant="outline" size="sm" onClick={handleResetLocal}>
                                        <RotateCcw className="mr-2 h-4 w-4" />
                                        Reset Locally
                                    </Button>
                                    <Button variant="destructive" size="sm" onClick={handleResetAndSave}>
                                        <RotateCcw className="mr-2 h-4 w-4" />
                                        Reset & Save
                                    </Button>
                                </>
                            )}
                            <Button onClick={handleSave} disabled={!isModified || isSubmitting || processing} size="sm">
                                <Save className="mr-2 h-4 w-4" />
                                {isSubmitting ? (
                                    <>
                                        <Spinner data-icon="inline-start" /> Saving...
                                    </>
                                ) : (
                                    'Save Changes'
                                )}
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

                {/* Instructions */}
                <Card className="border-blue-500/50 bg-blue-500/5">
                    <CardContent className="flex items-center gap-3 p-4">
                        <GripVertical className="h-5 w-5 text-blue-600" />
                        <p className="text-sm">
                            <strong>Tip:</strong> Drag the grip icon to reorder stages within each phase. Check/uncheck to include/exclude stages.
                        </p>
                    </CardContent>
                </Card>

                {/* Summary */}
                <div className="flex items-center gap-6 text-sm">
                    <div>
                        <span className="text-muted-foreground">Selected stages:</span> <span className="font-medium">{selectedStages.length}</span>
                    </div>
                    <div>
                        <span className="text-muted-foreground">Optional stages:</span> <span className="font-medium">{optionalStages.length}</span>
                    </div>
                    <div>
                        <span className="text-muted-foreground">Required stages:</span>{' '}
                        <span className="font-medium">{selectedStages.length - optionalStages.length}</span>
                    </div>
                </div>

                <Separator />

                {/* Stage Lists by Phase */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <PhaseCard title="Pre-Procurement" description="Planning & Preparation" stages={preProcurementStages} phase="pre_procurement" />
                    <PhaseCard title="Procurement" description="Bidding & Evaluation" stages={procurementStages} phase="procurement" />
                    <PhaseCard
                        title="Post-Procurement"
                        description="Award & Implementation"
                        stages={postProcurementStages}
                        phase="post_procurement"
                    />
                </div>
            </div>
        </AppLayout>
    );
}
