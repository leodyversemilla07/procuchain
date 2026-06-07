import { DatePickerInput } from '@/components/date-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { MapPin } from 'lucide-react';

interface DeliveryDetailsPanelProps {
    deliveryForm: {
        delivery_location: string;
        delivery_date: string;
        delivery_term_days: string;
    };
    isStageCompleted: boolean;
    deliveryDetailsSaved: boolean;
    isStageFuture: boolean;
    isSavingDelivery: boolean;
    onDeliveryFormChange: (field: 'delivery_location' | 'delivery_date' | 'delivery_term_days', value: string) => void;
    onSaveDeliveryDetails: () => void;
}

export function DeliveryDetailsPanel({
    deliveryForm,
    isStageCompleted,
    deliveryDetailsSaved,
    isStageFuture,
    isSavingDelivery,
    onDeliveryFormChange,
    onSaveDeliveryDetails,
}: DeliveryDetailsPanelProps) {
    return (
        <div className="mt-4 flex flex-col gap-4 border-t pt-6">
            <h4 className="text-muted-foreground flex items-center gap-2 text-xs font-bold uppercase">
                <MapPin className="h-3.5 w-3.5" />
                Delivery Info
            </h4>
            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-1">
                    <Label className="text-[10px] tracking-tighter uppercase opacity-70">Location</Label>
                    <Input
                        value={deliveryForm.delivery_location}
                        onChange={(event) => onDeliveryFormChange('delivery_location', event.target.value)}
                        disabled={isStageCompleted || deliveryDetailsSaved || isStageFuture}
                        className="bg-muted/30 h-8 text-xs"
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <Label className="text-[10px] tracking-tighter uppercase opacity-70">Date</Label>
                    <DatePickerInput
                        id="delivery_date"
                        value={deliveryForm.delivery_date}
                        onChange={(value) => onDeliveryFormChange('delivery_date', value)}
                        disabled={isStageCompleted || deliveryDetailsSaved || isStageFuture}
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <Label className="text-[10px] tracking-tighter uppercase opacity-70">Term (Days)</Label>
                    <Input
                        type="number"
                        value={deliveryForm.delivery_term_days}
                        onChange={(event) => onDeliveryFormChange('delivery_term_days', event.target.value)}
                        disabled={isStageCompleted || deliveryDetailsSaved || isStageFuture}
                        className="bg-muted/30 h-8 text-xs"
                    />
                </div>
                {!deliveryDetailsSaved && !isStageCompleted && !isStageFuture && (
                    <Button onClick={onSaveDeliveryDetails} disabled={isSavingDelivery} className="h-8 w-full text-xs">
                        {isSavingDelivery ? <Spinner /> : 'Save Details'}
                    </Button>
                )}
            </div>
        </div>
    );
}
