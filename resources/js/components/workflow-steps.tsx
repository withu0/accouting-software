import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

export interface WorkflowStep {
    id: string;
    label: string;
    description?: string;
}

interface WorkflowStepsProps {
    steps: WorkflowStep[];
    currentStep: string;
    className?: string;
}

export function WorkflowSteps({ steps, currentStep, className }: WorkflowStepsProps) {
    const currentIndex = steps.findIndex((s) => s.id === currentStep);

    return (
        <div className={cn('surface-card px-4 py-4 md:px-6', className)}>
            <ol className="flex flex-col gap-4 md:flex-row md:items-center md:gap-0">
                {steps.map((step, index) => {
                    const isComplete = index < currentIndex;
                    const isCurrent = step.id === currentStep;
                    const isLast = index === steps.length - 1;

                    return (
                        <li key={step.id} className={cn('flex flex-1 items-center gap-3', !isLast && 'md:pr-4')}>
                            <div className="flex items-center gap-3">
                                <div
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors',
                                        isComplete && 'bg-primary text-primary-foreground',
                                        isCurrent && 'bg-primary text-primary-foreground ring-4 ring-primary/20',
                                        !isComplete && !isCurrent && 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {isComplete ? <Check className="size-4" /> : index + 1}
                                </div>
                                <div className="min-w-0">
                                    <p
                                        className={cn(
                                            'text-sm font-semibold',
                                            isCurrent ? 'text-primary' : isComplete ? 'text-foreground' : 'text-muted-foreground',
                                        )}
                                    >
                                        {step.label}
                                    </p>
                                    {step.description && (
                                        <p className="text-muted-foreground hidden text-xs md:block">{step.description}</p>
                                    )}
                                </div>
                            </div>
                            {!isLast && (
                                <div
                                    className={cn(
                                        'hidden h-px flex-1 md:block',
                                        index < currentIndex ? 'bg-primary/40' : 'bg-border',
                                    )}
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

export const bankImportSteps: WorkflowStep[] = [
    { id: 'upload', label: 'アップロード', description: 'CSVを取り込む' },
    { id: 'review', label: '確認', description: '記帳内容を確認' },
    { id: 'complete', label: '記帳完了', description: '仕訳を登録' },
];
