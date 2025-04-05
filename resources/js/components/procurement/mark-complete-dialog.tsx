import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { FormLabel } from '@/components/ui/form';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import type { CheckedState } from '@radix-ui/react-checkbox';
import { toast } from 'sonner';
import { CheckCircle } from 'lucide-react';

interface FormData {
  [key: string]: string | boolean | undefined;  // More specific union type for form values
  remarks: string;
  confirmed: boolean;
}

interface MarkCompleteDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  procurementId: string;
  procurementTitle: string;
  onComplete?: () => void;
}

export function MarkCompleteDialog({
  open,
  onOpenChange,
  procurementId,
  procurementTitle,
  onComplete,
}: MarkCompleteDialogProps) {
  const { data, setData, post, processing, errors, reset } = useForm<FormData>({
    remarks: '',
    confirmed: false,
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    post(`/bac-secretariat/complete-procurement/${procurementId}`, {
      onSuccess: () => {
        toast.success('Procurement marked as complete', {
          description: `${procurementTitle} has been successfully marked as completed.`,
        });
        onOpenChange(false);
        reset();
        if (onComplete) {
          onComplete();
        }
      },
      onError: () => {
        toast.error('Failed to mark procurement as complete', {
          description: 'An error occurred while processing your request.',
        });
      }
    });
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[485px]">
        <DialogHeader>
          <div className="flex items-center">
            <CheckCircle className="h-5 w-5 text-green-500 mr-2" />
            <DialogTitle>Mark Procurement as Complete</DialogTitle>
          </div>
          <DialogDescription>
            This action will finalize the procurement process and mark it as completed.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1.5 border rounded-md p-3 bg-gray-50 dark:bg-gray-900">
            <h3 className="text-sm font-medium">Procurement Details</h3>
            <div className="text-sm">
              <div><strong>ID:</strong> {procurementId}</div>
              <div><strong>Title:</strong> {procurementTitle}</div>
            </div>
          </div>

          <div className="space-y-2">
            <FormLabel>Completion Remarks</FormLabel>
            <Textarea
              placeholder="Enter remarks about the completion of this procurement"
              value={data.remarks}
              onChange={e => setData('remarks', e.target.value)}
              className="resize-none min-h-[100px]"
            />
            {errors.remarks && (
              <div className="text-sm text-red-500">{errors.remarks}</div>
            )}
          </div>

          <div className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4 bg-amber-50 dark:bg-amber-950/20">
            <Checkbox
              checked={data.confirmed}
              onCheckedChange={(checked: CheckedState) => setData('confirmed', checked === true)}
            />
            <div className="space-y-1 leading-none">
              <FormLabel>
                I confirm that all procurement requirements have been fulfilled and this procurement process can be marked as complete
              </FormLabel>
              {errors.confirmed && (
                <div className="text-sm text-red-500">{errors.confirmed}</div>
              )}
            </div>
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={processing}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={processing}
              className="bg-green-600 hover:bg-green-700"
            >
              {processing ? 'Processing...' : 'Mark as Complete'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
