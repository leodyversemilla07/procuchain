import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import HeadingSmall from '@/components/heading-small';

import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { destroy } from '@/routes/settings/profile';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const {
        data,
        setData,
        delete: deleteRequest,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm<Required<{ password: string }>>({ password: '' });

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        deleteRequest(destroy.url(), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        clearErrors();
        reset();
    };

    return (
        <div className="flex flex-col gap-6">
            <HeadingSmall title="Delete account" description="Deactivate your account — data remains on-chain and recoverable" />
            <div className="flex flex-col gap-4 rounded-lg border border-red-100 bg-destructive/10 p-4 dark:border-red-200/10 dark:bg-destructive/10">
                <div className="relative flex flex-col gap-0.5 text-destructive dark:text-destructive">
                    <p className="font-medium">Warning</p>
                    <p className="text-sm">
                        Please proceed with caution. Your account will be deactivated but your data remains on-chain and can be restored by an
                        administrator.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger render={<Button variant="destructive" />}>Delete account</DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Are you sure you want to delete your account?</DialogTitle>
                        <DialogDescription>
                            Your account will be deactivated and you will lose access. However, your data is recorded on the blockchain and remains
                            recoverable by an authorized administrator. Please enter your password to confirm.
                        </DialogDescription>
                        <form className="flex flex-col gap-6" onSubmit={deleteUser}>
                            <div className="grid gap-2">
                                <Label htmlFor="password" className="sr-only">
                                    Password
                                </Label>

                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    ref={passwordInput}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Password"
                                    autoComplete="current-password"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose render={<Button variant="secondary" onClick={closeModal} />}>Cancel</DialogClose>

                                <Button variant="destructive" disabled={processing} type="submit" className="gap-2">
                                    {processing && <Spinner />}
                                    Delete account
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
