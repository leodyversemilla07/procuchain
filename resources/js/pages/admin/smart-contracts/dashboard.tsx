import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
  Shield, 
  Database, 
  CheckCircle, 
  AlertTriangle, 
  Activity, 
  FileText,
  Users,
  Clock,
  RefreshCw
} from 'lucide-react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { useSmartContractValidation } from '@/hooks/use-smart-contract-validation';

interface SmartContractDashboardProps {
  user?: {
    id: number;
    name: string;
    role: string;
  };
}

export default function SmartContractDashboard({ user }: SmartContractDashboardProps) {
  const [systemStatus, setSystemStatus] = useState<{
    success: boolean;
    message: string;
    data?: {
      blockchain_status: string;
      features: string[];
      blockchain_info?: any;
    };
    error?: string;
  } | null>(null);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const { getSystemStatus } = useSmartContractValidation();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Smart Contracts', href: '#' },
  ];

  const fetchSystemStatus = async () => {
    setIsRefreshing(true);
    try {
      const status = await getSystemStatus();
      setSystemStatus(status as any);
    } catch {
      toast.error('Failed to fetch system status');
    } finally {
      setIsRefreshing(false);
    }
  };

  const handleInitializeSystem = async () => {
    try {
      // Call initialize endpoint directly
      const response = await fetch('/smart-contracts/initialize', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });
      
      if (response.ok) {
        toast.success('Smart contract system initialized successfully');
        fetchSystemStatus();
      } else {
        throw new Error('Initialize failed');
      }
    } catch {
      toast.error('Failed to initialize smart contract system');
    }
  };

  useEffect(() => {
    fetchSystemStatus();
  }, []);

  const isSystemConnected = systemStatus?.data?.blockchain_status === 'connected';

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Smart Contract Management" />
      
      <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 bg-gradient-to-b from-background to-muted/20">
        <div className="flex items-center justify-between">
          <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2 text-primary">
              <Shield className="h-6 w-6" />
              <h1 className="text-2xl font-bold">Smart Contract Management</h1>
            </div>
            <p className="text-muted-foreground">
              Monitor and manage the blockchain-based document validation system
            </p>
          </div>
          
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={fetchSystemStatus}
              disabled={isRefreshing}
            >
              <RefreshCw className={`h-4 w-4 mr-2 ${isRefreshing ? 'animate-spin' : ''}`} />
              Refresh
            </Button>
            
            {user?.role === 'admin' && (
              <Button
                onClick={handleInitializeSystem}
                disabled={isSystemConnected}
              >
                <Database className="h-4 w-4 mr-2" />
                Initialize System
              </Button>
            )}
          </div>
        </div>

        {/* System Status Overview */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center gap-3">
                <div className={`p-2 rounded-full ${
                  isSystemConnected ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'
                }`}>
                  <Database className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Blockchain Status</p>
                  <p className="text-lg font-bold">
                    {isSystemConnected ? 'Connected' : 'Disconnected'}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-full bg-blue-100 text-blue-600">
                  <FileText className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Active Features</p>
                  <p className="text-lg font-bold">
                    {systemStatus?.data?.features?.length || 0}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-full bg-purple-100 text-purple-600">
                  <Activity className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">System Health</p>
                  <p className="text-lg font-bold">
                    {systemStatus?.success ? 'Healthy' : 'Issues'}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-full bg-orange-100 text-orange-600">
                  <Clock className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Last Updated</p>
                  <p className="text-lg font-bold">
                    {new Date().toLocaleTimeString()}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Detailed Status */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Shield className="h-5 w-5" />
                System Features
              </CardTitle>
              <CardDescription>
                Available smart contract capabilities
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {systemStatus?.data?.features?.map((feature: string, index: number) => (
                <div key={index} className="flex items-center justify-between">
                  <span className="text-sm capitalize">
                    {feature.replace(/_/g, ' ')}
                  </span>
                  <Badge variant="default" className="bg-green-100 text-green-700">
                    <CheckCircle className="h-3 w-3 mr-1" />
                    Active
                  </Badge>
                </div>
              )) || (
                <p className="text-muted-foreground text-sm">No features loaded</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Database className="h-5 w-5" />
                Blockchain Information
              </CardTitle>
              <CardDescription>
                Connection and network details
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {systemStatus?.data?.blockchain_info ? (
                <div className="space-y-2">
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Network:</span>
                    <span className="text-sm font-medium">MultiChain</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Status:</span>
                    <Badge variant={isSystemConnected ? "default" : "destructive"}>
                      {isSystemConnected ? 'Connected' : 'Disconnected'}
                    </Badge>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Last Check:</span>
                    <span className="text-sm font-medium">
                      {new Date().toLocaleString()}
                    </span>
                  </div>
                </div>
              ) : (
                <p className="text-muted-foreground text-sm">No blockchain information available</p>
              )}
            </CardContent>
          </Card>
        </div>

        {/* System Messages */}
        {systemStatus && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                {systemStatus.success ? (
                  <CheckCircle className="h-5 w-5 text-green-600" />
                ) : (
                  <AlertTriangle className="h-5 w-5 text-red-600" />
                )}
                System Status
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className={`p-4 rounded-lg ${
                systemStatus.success 
                  ? 'bg-green-50 border border-green-200' 
                  : 'bg-red-50 border border-red-200'
              }`}>
                <p className={`text-sm ${
                  systemStatus.success ? 'text-green-800' : 'text-red-800'
                }`}>
                  {systemStatus.message}
                </p>
                {systemStatus.error && (
                  <p className="text-sm text-red-600 mt-2">
                    Error: {systemStatus.error}
                  </p>
                )}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
            <CardDescription>
              Common administrative tasks
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Button variant="outline" className="justify-start">
                <FileText className="h-4 w-4 mr-2" />
                View Audit Logs
              </Button>
              <Button variant="outline" className="justify-start">
                <Users className="h-4 w-4 mr-2" />
                Manage Permissions
              </Button>
              <Button variant="outline" className="justify-start">
                <Activity className="h-4 w-4 mr-2" />
                System Diagnostics
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
