import React from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Edit, Users, Mail, Shield, Key } from 'lucide-react';

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
    setFormData: React.Dispatch<React.SetStateAction<{
        name: string;
        email: string;
        role: string;
        password: string;
        password_confirmation: string;
        blockchain_address: string;
    }>>;
    roles: string[];
    onSubmit: (e: React.FormEvent) => void;
    getRoleDisplayName: (role: string) => string;
}

export default function EditUserDialog({
    open,
    onOpenChange,
    formData,
    setFormData,
    roles,
    onSubmit,
    getRoleDisplayName,
}: EditUserDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px] p-0 gap-0">
                <DialogHeader className="px-6 py-6 pb-4 border-b">
                    <div className="flex items-center space-x-3">
                        <div className="h-10 w-10 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
                            <Edit className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <DialogTitle className="text-xl font-semibold text-foreground">Edit User</DialogTitle>
                            <DialogDescription className="text-sm text-muted-foreground mt-1">
                                Update user information and access permissions
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                
                <ScrollArea className="h-[calc(90vh-180px)] px-6">
                    <form onSubmit={onSubmit} className="space-y-6 pb-6 pt-6">
                        {/* Personal Information Section */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <div className="flex items-center space-x-2">
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    <CardTitle className="text-base">Personal Information</CardTitle>
                                </div>
                                <CardDescription className="text-xs text-muted-foreground">
                                    Update user's basic details and contact information
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_name" className="text-sm font-medium flex items-center">
                                            <Users className="h-3 w-3 mr-1" />
                                            Full Name
                                            <span className="text-destructive ml-1">*</span>
                                        </Label>
                                        <Input
                                            id="edit_name"
                                            className="h-11 focus:ring-2 focus:ring-primary/20"
                                            placeholder="Enter full name"
                                            value={formData.name}
                                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_email" className="text-sm font-medium flex items-center">
                                            <Mail className="h-3 w-3 mr-1" />
                                            Email Address
                                            <span className="text-destructive ml-1">*</span>
                                        </Label>
                                        <Input
                                            id="edit_email"
                                            type="email"
                                            className="h-11 focus:ring-2 focus:ring-primary/20"
                                            placeholder="Enter email address"
                                            value={formData.email}
                                            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_role" className="text-sm font-medium flex items-center">
                                        <Shield className="h-3 w-3 mr-1" />
                                        Role & Permissions
                                        <span className="text-destructive ml-1">*</span>
                                    </Label>
                                    <Select value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })}>
                                        <SelectTrigger className="h-11 focus:ring-2 focus:ring-primary/20">
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
                                    <Key className="h-4 w-4 text-muted-foreground" />
                                    <CardTitle className="text-base text-muted-foreground">Security & Access</CardTitle>
                                    <Badge variant="secondary" className="text-xs">Optional</Badge>
                                </div>
                                <CardDescription className="text-xs text-muted-foreground">
                                    Update login credentials (leave blank to keep current password)
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_password" className="text-sm font-medium text-muted-foreground">
                                            New Password
                                        </Label>
                                        <Input
                                            id="edit_password"
                                            type="password"
                                            className="h-11 focus:ring-2 focus:ring-primary/20"
                                            placeholder="Leave blank to keep current"
                                            value={formData.password}
                                            onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_password_confirmation" className="text-sm font-medium text-muted-foreground">
                                            Confirm New Password
                                        </Label>
                                        <Input
                                            id="edit_password_confirmation"
                                            type="password"
                                            className="h-11 focus:ring-2 focus:ring-primary/20"
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
                                    <div className="h-4 w-4 rounded border-2 border-muted-foreground flex items-center justify-center">
                                        <div className="h-1 w-1 bg-muted-foreground rounded-full"></div>
                                    </div>
                                    <CardTitle className="text-base text-muted-foreground">Blockchain Integration</CardTitle>
                                    <Badge variant="secondary" className="text-xs">Optional</Badge>
                                </div>
                                <CardDescription className="text-xs text-muted-foreground">
                                    Update blockchain address for enhanced security features
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_blockchain_address" className="text-sm font-medium text-muted-foreground">
                                        Blockchain Address
                                    </Label>
                                    <Input
                                        id="edit_blockchain_address"
                                        className="h-11 focus:ring-2 focus:ring-primary/20 font-mono text-sm"
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
                <div className="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-6 border-t bg-muted/30 dark:bg-muted/20 px-6 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 px-6 order-2 sm:order-1"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        className="h-11 px-6 shadow-md order-1 sm:order-2"
                        onClick={onSubmit}
                    >
                        <Edit className="h-4 w-4 mr-2" />
                        Update User
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
