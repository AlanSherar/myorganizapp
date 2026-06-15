import { Request, Response, NextFunction } from 'express';

export const mockAuth = (req: Request, res: Response, next: NextFunction) => {
    // Inject a mock user into the request body for controllers to use
    // In a real app, this would be populated by JWT verification
    if (!req.body.user) {
        req.body.user = { id: '60d0fe4f5311236168a109ca', name: 'Demo User' };
    }
    next();
};
