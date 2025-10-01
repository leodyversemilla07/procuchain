import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Edit, Key, Mail, Shield, Users } from 'lucide-react';
import React from 'react';

interface EditUserDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    formData: {
        name: string;
        email: string;
        role: string;
        password: string;
        password_confirmation: string;
        blockchain_address: string;
    };
    setFormData: React.Dispatch<
        React.SetStateAction<{
            name: string;
            email: string;
            role: string;
            password: string;
            password_confirmation: string;
            blockchain_address: string;
        }>
    >;
    roles: string[];
    onSubmit: (e: React.FormEvent) => void;
    getRoleDisplayName: (role: string) => string;
}

export default function EditUserDialog({ open, onOpenChange, formData, setFormData, roles, onSubmit, getRoleDisplayName }: EditUserDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 p-0 sm:max-w-[600px]">
                <DialogHeader className="border-b px-6 py-6 pb-4">
                    <div className="flex items-center space-x-3">
                        <div className="bg-primary/10 dark:bg-primary/20 flex h-10 w-10 items-center justify-center rounded-lg">
                            <Edit className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-foreground text-xl font-semibold">Edit User</DialogTitle>
                            <DialogDescription className="text-muted-foreground mt-1 text-sm">
                                Update user information and access permissions
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <ScrollArea className="h-[calc(90vh-180px)] px-6">
                    <form onSubmit={onSubmit} className="space-y-6 pt-6 pb-6">
                        {/* Personal Information Section */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <div className="flex items-center space-x-2">
                                    <Users className="text-muted-foreground h-4 w-4" />
                                    <CardTitle className="text-base">Personal Information</CardTitle>
                                </div>
                                <CardDescription className="text-muted-foreground text-xs">
                                    Update user's basic details and contact information
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_name" className="flex items-center text-sm font-medium">
                                            <Users className="mr-1 h-3 w-3" />
                                            Full Name
                                            <span className="text-destructive ml-1">*</span>
                                        </Label>
                                        <Input
                                            id="edit_name"
                                            className="focus:ring-primary/20 h-11 focus:ring-2"
                                            placeholder="Enter full name"
                                            value={formData.name}
                                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_email" className="flex items-center text-sm font-medium">
                                            <Mail className="mr-1 h-3 w-3" />
                                            Email Address
                                            <span className="text-destructive ml-1">*</span>
                                        </Label>
                                        <Input
                                            id="edit_email"
                                            type="email"
                                            className="focus:ring-primary/20 h-11 focus:ring-2"
                                            placeholder="Enter email address"
                                            value={formData.email}
                                            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_role" className="flex items-center text-sm font-medium">
                                        <Shield className="mr-1 h-3 w-3" />
                                        Role & Permissions
                                        <span className="text-destructive ml-1">*</span>
                                    </Label>
                                    <Select value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })}>
                                        <SelectTrigger className="focus:ring-primary/20 h-11 focus:ring-2">
                                            <SelectValue placeholder="Select a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem key={role} value={role}>
                                                    {getRoleDisplayName(role)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Security Information Section */}
                        <Card className="border-border border-dashed">
                            <CardHeader className="pb-3">
                                <div className="flex items-center space-x-2">
                                    <Key className="text-muted-foreground h-4 w-4" />
                                    <CardTitle className="text-muted-foreground text-base">Security & Access</CardTitle>
                                    <Badge variant="secondary" className="text-xs">
                                        Optional
                                    </Badge>
                                </div>
                                <CardDescription className="text-muted-foreground text-xs">
                                    Update login credentials (leave blank to keep current password)
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_password" className="text-muted-foreground text-sm font-medium">
                                            New Password
                                        </Label>
                                        <Input
                                            id="edit_password"
                                            type="password"
                                            className="focus:ring-primary/20 h-11 focus:ring-2"
                                            placeholder="Leave blank to keep current"
                                            value={formData.password}
                                            onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_password_confirmation" className="text-muted-foreground text-sm font-medium">
                                            Confirm New Password
                                        </Label>
                                        <Input
                                            id="edit_password_confirmation"
                                            type="password"
                                            className="focus:ring-primary/20 h-11 focus:ring-2"
                                            placeholder="Confirm new password"
                                            value={formData.password_confirmation}
                                            onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Blockchain Information Section */}
                        <Card className="border-border border-dashed">
                            <CardHeader className="pb-3">
                                <div className="flex items-center space-x-2">
                                    <div className="border-muted-foreground flex h-4 w-4 items-center justify-center rounded border-2">
                                        <div className="bg-muted-foreground h-1 w-1 rounded-full"></div>
                                    </div>
                                    <CardTitle className="text-muted-foreground text-base">Blockchain Integration</CardTitle>
                                    <Badge variant="secondary" className="text-xs">
                                        Optional
                                    </Badge>
                                </div>
                                <CardDescription className="text-muted-foreground text-xs">
                                    Update blockchain address for enhanced security features
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_blockchain_address" className="text-muted-foreground text-sm font-medium">
                                        Blockchain Address
                                    </Label>
                                    <Input
                                        id="edit_blockchain_address"
                                        className="focus:ring-primary/20 h-11 font-mono text-sm focus:ring-2"
                                        placeholder="0x... (optional blockchain address)"
                                        value={formData.blockchain_address}
                                        onChange={(e) => setFormData({ ...formData, blockchain_address: e.target.value })}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </ScrollArea>

                {/* Action Buttons */}
                <div className="bg-muted/30 dark:bg-muted/20 flex flex-col justify-end space-y-2 border-t px-6 py-4 pt-6 sm:flex-row sm:space-y-0 sm:space-x-3">
                    <Button type="button" variant="outline" className="order-2 h-11 px-6 sm:order-1" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="submit" className="order-1 h-11 px-6 shadow-md sm:order-2" onClick={onSubmit}>
                        <Edit className="mr-2 h-4 w-4" />
                        Update User
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
