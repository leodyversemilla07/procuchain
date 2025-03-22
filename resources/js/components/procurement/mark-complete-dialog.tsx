import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { toast } from 'sonner';
import { CheckCircle } from 'lucide-react';

const formSchema = z.object({
  completionRemarks: z.string().min(1, 'Completion remarks are required'),
  confirmCompletion: z.boolean().refine(val => val === true, {
    message: 'You must confirm that all requirements have been fulfilled',
  }),
});

type FormValues = z.infer<typeof formSchema>;

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
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      completionRemarks: '',
      confirmCompletion: false,
    },
  });

  function onSubmit(values: FormValues) {
    setIsSubmitting(true);
    
    router.post(
      `/bac-secretariat/complete-procurement/${procurementId}`,
      {
        remarks: values.completionRemarks,
        confirmed: values.confirmCompletion,
      },
      {
        onSuccess: () => {
          toast.success('Procurement marked as complete', {
            description: `${procurementTitle} has been successfully marked as completed.`,
          });
          onOpenChange(false);
          form.reset();
          if (onComplete) {
            onComplete();
          }
        },
        onError: (errors) => {
          toast.error('Failed to mark procurement as complete', {
            description: errors.remarks || errors.confirmed || 'An error occurred while processing your request.',
          });
        },
        onFinish: () => {
          setIsSubmitting(false);
        },
      }
    );
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
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <div className="space-y-1.5 border rounded-md p-3 bg-gray-50 dark:bg-gray-900">
              <h3 className="text-sm font-medium">Procurement Details</h3>
              <div className="text-sm">
                <div><strong>ID:</strong> {procurementId}</div>
                <div><strong>Title:</strong> {procurementTitle}</div>
              </div>
            </div>
            
            <FormField
              control={form.control}
              name="completionRemarks"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Completion Remarks</FormLabel>
                  <FormControl>
                    <Textarea
                      placeholder="Enter remarks about the completion of this procurement"
                      {...field}
                      className="resize-none min-h-[100px]"
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="confirmCompletion"
              render={({ field }) => (
                <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4 bg-amber-50 dark:bg-amber-950/20">
                  <FormControl>
                    <Checkbox
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                  <div className="space-y-1 leading-none">
                    <FormLabel>
                      I confirm that all procurement requirements have been fulfilled and this procurement process can be marked as complete
                    </FormLabel>
                  </div>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                Cancel
              </Button>
              <Button type="submit" disabled={isSubmitting} className="bg-green-600 hover:bg-green-700">
                {isSubmitting ? 'Processing...' : 'Mark as Complete'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
